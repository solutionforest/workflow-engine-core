<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Attributes\Retry;
use SolutionForest\WorkflowEngine\Attributes\Timeout;
use SolutionForest\WorkflowEngine\Attributes\WorkflowStep;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Support\Arr;

/**
 * HTTP request action with PHP 8.3+ features
 */
#[WorkflowStep(
    id: 'http_request',
    name: 'HTTP Request',
    description: 'Makes HTTP requests to external APIs'
)]
#[Timeout(seconds: 30)]
#[Retry(attempts: 3, backoff: 'exponential')]
class HttpAction extends BaseAction
{
    public function getName(): string
    {
        return 'HTTP Request';
    }

    public function getDescription(): string
    {
        return 'Makes HTTP requests to external APIs with retry logic';
    }

    protected function doExecute(WorkflowContext $context): ActionResult
    {
        $url = $this->getConfig('url');
        $method = strtoupper($this->getConfig('method', 'GET'));
        $data = $this->getConfig('data', []);
        $headers = $this->getConfig('headers', []);
        $timeout = (int) $this->getConfig('timeout', 30);
        $connectTimeout = (int) $this->getConfig('connect_timeout', min(10, $timeout));
        $verifyTls = (bool) $this->getConfig('verify_tls', true);
        $maxRedirects = (int) $this->getConfig('max_redirects', 3);

        if (! $url) {
            return ActionResult::failure('URL is required for HTTP action');
        }

        // Process template variables in URL and data
        $url = $this->processTemplate($url, $context->getData());
        $data = $this->processArrayTemplates($data, $context->getData());

        try {
            // Initialize cURL
            $ch = curl_init();

            // Build query string for GET requests
            if ($method === 'GET' && ! empty($data)) {
                $url .= '?'.http_build_query($data);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $maxRedirects > 0);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $maxRedirects);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyTls);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyTls ? 2 : 0);
            // Restrict redirects to HTTP/HTTPS so a Location header cannot
            // hand the request off to file://, gopher://, etc. The constants
            // were renamed in cURL 7.85 — prefer the new one when present.
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS_STR, 'http,https');
            } elseif (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                $allowed = CURLPROTO_HTTP | CURLPROTO_HTTPS;
                curl_setopt($ch, CURLOPT_PROTOCOLS, $allowed);
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $allowed);
            }

            // Set HTTP method and body
            if ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if (! empty($data)) {
                    $jsonData = json_encode($data);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                    $headers['Content-Type'] = 'application/json';
                    $headers['Content-Length'] = strlen($jsonData);
                }
            }

            // Set headers
            if (! empty($headers)) {
                $headerArray = [];
                foreach ($headers as $key => $value) {
                    $headerArray[] = "{$key}: {$value}";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            }

            // Capture response headers
            $responseHeaders = [];
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) >= 2) {
                    $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                }

                return $len;
            });

            // Execute request
            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false || $error) {
                return ActionResult::failure(
                    'HTTP request failed: '.($error ?: 'unknown cURL error'),
                    [
                        'error' => $error,
                        'url' => $url,
                        'method' => $method,
                    ]
                );
            }

            // Parse JSON response
            $responseData = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $responseData = $responseBody;
            }

            // Check if successful (2xx status code)
            if ($statusCode >= 200 && $statusCode < 300) {
                return ActionResult::success([
                    'status_code' => $statusCode,
                    'response_data' => $responseData,
                    'headers' => $responseHeaders,
                    'url' => $url,
                    'method' => $method,
                ]);
            }

            return ActionResult::failure(
                "HTTP request failed with status {$statusCode}",
                [
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'url' => $url,
                    'method' => $method,
                ]
            );

        } catch (\Throwable $e) {
            return ActionResult::failure(
                "HTTP request exception: {$e->getMessage()}",
                [
                    'exception' => $e->getMessage(),
                    'url' => $url,
                    'method' => $method,
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processTemplate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function ($matches) use ($data) {
            return Arr::get($data, trim($matches[1]), $matches[0]);
        }, $template);
    }

    /**
     * @param array<string, mixed> $array
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function processArrayTemplates(array $array, array $data): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $result[$key] = $this->processTemplate($value, $data);
            } elseif (is_array($value)) {
                $result[$key] = $this->processArrayTemplates($value, $data);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
