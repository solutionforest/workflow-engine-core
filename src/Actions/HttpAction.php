<?php

namespace SolutionForest\WorkflowEngine\Actions;

use Illuminate\Support\Facades\Http;
use SolutionForest\WorkflowEngine\Attributes\Retry;
use SolutionForest\WorkflowEngine\Attributes\Timeout;
use SolutionForest\WorkflowEngine\Attributes\WorkflowStep;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

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
        $timeout = $this->getConfig('timeout', 30);

        if (! $url) {
            return ActionResult::failure('URL is required for HTTP action');
        }

        // Process template variables in URL and data
        $url = $this->processTemplate($url, $context->getData());
        $data = $this->processArrayTemplates($data, $context->getData());

        try {
            $response = match ($method) {
                'GET' => Http::timeout($timeout)->withHeaders($headers)->get($url, $data),
                'POST' => Http::timeout($timeout)->withHeaders($headers)->post($url, $data),
                'PUT' => Http::timeout($timeout)->withHeaders($headers)->put($url, $data),
                'PATCH' => Http::timeout($timeout)->withHeaders($headers)->patch($url, $data),
                'DELETE' => Http::timeout($timeout)->withHeaders($headers)->delete($url, $data),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
            };

            if ($response->successful()) {
                return ActionResult::success([
                    'status_code' => $response->status(),
                    'response_data' => $response->json(),
                    'headers' => $response->headers(),
                    'url' => $url,
                    'method' => $method,
                ]);
            }

            return ActionResult::failure(
                "HTTP request failed with status {$response->status()}: {$response->body()}",
                [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'url' => $url,
                    'method' => $method,
                ]
            );

        } catch (\Exception $e) {
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
     * @param  array<string, mixed>  $data
     */
    private function processTemplate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function ($matches) use ($data) {
            return data_get($data, trim($matches[1]), $matches[0]);
        }, $template);
    }

    /**
     * @param  array<string, mixed>  $array
     * @param  array<string, mixed>  $data
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
