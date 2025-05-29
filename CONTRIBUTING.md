# Contributing to Workflow Engine Core

Thank you for considering contributing to the Workflow Engine Core! We welcome all types of contributions, from bug reports and feature requests to code contributions and documentation improvements.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Making Changes](#making-changes)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Release Process](#release-process)

## 🤝 Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to [info@solutionforest.com](mailto:info@solutionforest.com).

### Our Standards

- Use welcoming and inclusive language
- Be respectful of differing viewpoints and experiences
- Gracefully accept constructive criticism
- Focus on what is best for the community
- Show empathy towards other community members

## 🚀 Getting Started

### Prerequisites

- PHP 8.3 or higher
- Composer
- Git

### Types of Contributions

We welcome many types of contributions:

- 🐛 **Bug Reports**: Report bugs you've found
- 💡 **Feature Requests**: Suggest new features or improvements
- 📝 **Documentation**: Improve or add documentation
- 🛠️ **Code**: Fix bugs or implement new features
- 🧪 **Tests**: Add or improve test coverage
- 🎨 **Design**: Improve UX/UI or visual design

## 🛠️ Development Setup

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/workflow-engine-core.git
   cd workflow-engine-core
   ```

3. **Install dependencies**:
   ```bash
   composer install
   ```

4. **Create a branch** for your changes:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b bugfix/issue-number
   ```

## 🔄 Making Changes

### Branch Naming

Use descriptive branch names:
- `feature/add-retry-mechanism`
- `bugfix/fix-memory-leak`
- `docs/update-installation-guide`
- `refactor/simplify-state-manager`

### Commit Messages

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Changes that do not affect the meaning of the code
- `refactor`: A code change that neither fixes a bug nor adds a feature
- `perf`: A code change that improves performance
- `test`: Adding missing tests or correcting existing tests
- `chore`: Changes to the build process or auxiliary tools

**Examples:**
```
feat(core): add retry mechanism for failed actions
fix(executor): resolve memory leak in long-running workflows
docs(readme): update installation instructions
test(integration): add workflow cancellation tests
```

## 📏 Coding Standards

### PHP Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
- Use strict typing: `declare(strict_types=1);`
- Use PHP 8.3+ features where appropriate
- Write self-documenting code with clear variable and method names

### Code Style

- Use 4 spaces for indentation (no tabs)
- Line length should not exceed 120 characters
- Use meaningful variable and method names
- Add PHPDoc comments for all public methods and properties

### Example:

```php
<?php

declare(strict_types=1);

namespace SolutionForest\WorkflowEngine\Core;

/**
 * Manages workflow execution and state transitions.
 */
final class WorkflowEngine
{
    /**
     * Start a new workflow instance.
     *
     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowContext $context The execution context
     * @return WorkflowInstance The created workflow instance
     * @throws WorkflowException When workflow cannot be started
     */
    public function start(WorkflowDefinition $workflow, WorkflowContext $context): WorkflowInstance
    {
        // Implementation
    }
}
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run specific test file
./vendor/bin/pest tests/Unit/WorkflowEngineTest.php

# Run tests with specific filter
./vendor/bin/pest --filter="it can start a workflow"
```

### Writing Tests

- Write tests for all new features and bug fixes
- Use descriptive test names that explain what is being tested
- Follow the Arrange-Act-Assert pattern
- Use Pest PHP testing framework

**Example:**

```php
<?php

declare(strict_types=1);

use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;

it('can start a workflow with initial context', function () {
    // Arrange
    $workflow = WorkflowBuilder::create('test-workflow')
        ->addStep('start', TestAction::class)
        ->build();
    
    $engine = new WorkflowEngine();
    $context = new WorkflowContext('test-workflow', 'start', ['key' => 'value']);
    
    // Act
    $instance = $engine->start($workflow, $context);
    
    // Assert
    expect($instance->getState())->toBe(WorkflowState::RUNNING);
    expect($instance->getCurrentStep())->toBe('start');
});
```

### Test Coverage

- Aim for at least 90% code coverage
- Test both happy path and edge cases
- Include integration tests for complex workflows

## 🚀 Submitting Changes

### Before Submitting

1. **Ensure tests pass**:
   ```bash
   composer test
   ```

2. **Run static analysis**:
   ```bash
   composer analyze
   ```

3. **Check code style**:
   ```bash
   composer format:check
   ```

4. **Update documentation** if needed

### Pull Request Process

1. **Update your branch** with the latest changes from main:
   ```bash
   git fetch origin
   git rebase origin/main
   ```

2. **Push your changes**:
   ```bash
   git push origin your-branch-name
   ```

3. **Create a Pull Request** on GitHub with:
   - Clear title and description
   - Reference any related issues
   - Screenshots if applicable
   - Checklist of changes made

### Pull Request Template

```markdown
## Description
Brief description of the changes made.

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes

## Checklist
- [ ] My code follows the style guidelines of this project
- [ ] I have performed a self-review of my own code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have made corresponding changes to the documentation
- [ ] My changes generate no new warnings
```

## 📦 Release Process

### Versioning

We use [Semantic Versioning](https://semver.org/):
- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

### Release Workflow

1. Update `CHANGELOG.md`
2. Create a release branch: `release/vX.Y.Z`
3. Update version numbers
4. Create a pull request
5. After merge, tag the release
6. Publish to Packagist

## 🆘 Getting Help

If you need help:

1. **Check existing issues** on GitHub
2. **Search the documentation**
3. **Ask questions** in GitHub Discussions
4. **Join our community** chat (if available)

## 🙏 Recognition

Contributors will be recognized in:
- The project's README
- Release notes
- Our website (coming soon)

Thank you for contributing to Workflow Engine Core! 🎉
