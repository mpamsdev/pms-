<?php
namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;

class RegistrationStepMiddleware implements MiddlewareInterface
{
    private array $stepRequirements;

    /**
     * @param array $stepRequirements
     * An array mapping route paths to required completed steps in session.
     * Example:
     * [
     *   '/register/step-two' => ['step1'],
     *   '/register/step-three' => ['step1', 'step2'],
     *   ...
     * ]
     */
    public function __construct(array $stepRequirements)
    {
        $this->stepRequirements = $stepRequirements;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri()->getPath();
        session_start();

        if (isset($this->stepRequirements[$uri])) {
            $requiredSteps = $this->stepRequirements[$uri];

            // Determine if it's an individual or organisation route
            $sessionKey = str_starts_with($uri, '/account/org') ? 'organisation_registration' : 'registration';
            $sessionSteps = $_SESSION[$sessionKey] ?? [];

            // Check for each required step
            foreach ($requiredSteps as $step) {
                if (!isset($sessionSteps[$step])) {
                    $redirectPath = str_starts_with($uri, '/account/org') ? '/account/org/step-one' : '/account/ind/step-one';
                    $response = new \Slim\Psr7\Response();
                    return $response->withHeader('Location', $redirectPath)->withStatus(302);
                }
            }
        }

        return $handler->handle($request);
    }

}

