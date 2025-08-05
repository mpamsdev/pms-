<?php

namespace App\Controllers;

use App\Models\companies;
use App\Models\employees;
use App\Models\admins;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Respect\Validation\Validator as v;

class AuthController
{
    protected Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    // Render login page
    public function login(Request $request, Response $response, array $args): Response
    {
        return $this->view->render($response, 'auth/login.twig', [
            'title' => 'Login ',
        ]);
    }

    // Handle login logic
    public function getLogin(Request $request, Response $response, array $args): Response
    {
        session_start();
        $data = $request->getParsedBody();

        //var_dump ($data);
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        // Validate input
        $emailValidator = v::email()->notEmpty();
        $passwordValidator = v::stringType()->notEmpty();

        if (!$emailValidator->validate($email) || !$passwordValidator->validate($password)) {
            return $this->view->render($response, 'auth/login.twig', [
                'title' => 'Login',
                'errors' => 'Invalid email or password format.'
            ]);
        }

        // Try to find user in the `users` table
        $user = employees::where('username', $email)->first();

        if ($user && password_verify($password, $user->password)) {
            if ($user->status === 'suspended') {
                // Show error message for suspended account
                $_SESSION['error'] = 'Your account has been suspended. Please contact support.';
                return $response->withHeader('Location', '/')->withStatus(302);
            }

            // Proceed with login for active users
            $_SESSION['userid'] = $user->employee_number;
            $_SESSION['username'] = $user->username;
            $_SESSION['profile_picture'] = $user->profile_picture;
            $_SESSION['role'] = 'user';
            $_SESSION['login_time'] = time();
            $_SESSION['session_timeout'] = 1800;
            $_SESSION['userData'] = $user->toArray();
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        // If not found in users, check the `admins` table
        $admin = admins::where('username', $email)->first();

        if ($admin && password_verify($password, $admin->password)) {

            if ($admin->status === 'suspended') {
                $_SESSION['error'] = 'Admin account suspended. Contact system administrator.';
                return $response->withHeader('Location', '/')->withStatus(302);
            }

            $_SESSION['userid'] = $admin->uuid;
            $_SESSION['username'] = $admin->username;
            $_SESSION['profile_picture'] = $admin->profile_picture;
            $_SESSION['name'] = $admin->name;
            $_SESSION['login_time'] = time();
            $_SESSION['session_timeout'] = 1800;
            $_SESSION['userData'] = $admin->toArray();
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        return $this->view->render($response, 'auth/login.twig', [
            'title' => 'Login',
            'errors' => 'Invalid email or password.'
        ]);
    }

    // Render register page
    public function showRegister(Request $request, Response $response, array $args): Response
    {
        return $this->view->render($response, 'auth/sign-up.twig', [
            'title' => 'Sign up ',
        ]);
    }


    public function register(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();

        // Extract fields
        $name         = trim($data['name'] ?? '');
        $email        = trim($data['email'] ?? '');
        $password     = $data['password'] ?? '';
        $phone        = trim($data['phone'] ?? '');
        //$organization = trim($data['company'] ?? '');
        //$title        = trim($data['title'] ?? '');

        // Validation
        $validator = [
            'name'         => v::stringType()->notEmpty(),
            'email'        => v::email()->notEmpty(),
            'password'     => v::stringType()->length(6, null),
            'phone'        => v::phone()->notEmpty(),
            //'organization' => v::stringType()->notEmpty(),
            //'title'        => v::stringType()->notEmpty(),
        ];

        $errors = [];

        foreach ($validator as $field => $rule) {
            if (!$rule->validate($$field)) {
                $errors[$field] = ucfirst($field) . ' is invalid or missing.';
            }
        }

        if (!empty($errors)) {
            return $this->view->render($response, 'auth/sign-up.twig', [
                'title' => 'Sign Up',
                'errors' => $errors
            ]);
        }

        if (employees::WHERE('email', $email)->exists()) {
            return $this->view->render($response, 'auth/sign-up.twig', [
                'title' => 'Sign Up',
                'error' => 'Email already registered.'
            ]);
        }

        // Create the user
        $user = new employees();
        $user->name         = $name;
        $user->email        = $email;
        $user->password_hash     = password_hash($password, PASSWORD_DEFAULT);
        $user->phone        = $phone;
        //$user->organization = $organization;
        //$user->title        = $title;
        $user->save();

        return $response->withHeader('Location', '/auth/login')->withStatus(302);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $data  = $request->getParsedBody();
        $input = trim($data['username']); // could be email or phone

        $validator = [
            'username' => v::oneOf(
                v::email(),
                v::phone()
            )->notEmpty()
        ];


        $errors = [];

        foreach ($validator as $field => $rule) {
            if (!$rule->validate($input)) {
                $errors[$field] = ucfirst($field) . ' is invalid or missing.';
            }
        }

        if (!empty($errors)) {
            return $this->view->render($response, 'auth/forgot-password.twig', [
                'title' => 'Forgot Password',
                'pageName' => 'Forgot Password',
                'errors' => $errors
            ]);
        }

        // Search in Users and Companies tables
        $user = employees::where('username', $input)->orWhere('phone', $input)->first();
        $company = Company::where('username', $input)->orWhere('phone', $input)->first();

        if (!$user && !$company) {
            return $this->view->render($response, 'auth/forgot-password.twig', [
                'title' => 'Forgot Password',
                'error' => 'No account associated with that email or phone number.'
            ]);
        }

        // Determine contact method and recipient
        $recipientPhone = $user->phone ?? $company->phone ?? null;
        $recipientEmail = $user->username ?? $company->username ?? null;

        $code = rand(1000, 9999); // 4-digit code
        $_SESSION['password_reset'] = [
            'recipient' => $input,
            'code' => $code,
            'expires_at' => time() + (10 * 60) // 10 minutes from now
        ];

        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            // Send via PHPMailer
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.example.com'; // Set your SMTP
                $mail->SMTPAuth   = true;
                $mail->Username   = 'your@email.com';
                $mail->Password   = 'yourpassword';
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('noreply@example.com', 'Support');
                $mail->addAddress($recipientEmail);
                $mail->Subject = 'Your Password Reset Code';
                $mail->Body    = "Your password reset code is: $code. It expires in 10 minutes.";

                $mail->send();
            } catch (Exception $e) {
                return $this->view->render($response, 'auth/forgot-password.twig', [
                    'title' => 'Forgot Password',
                    'error' => 'Failed to send email. Please try again later.'
                ]);
            }
        } else {
            // Send via SMS API (Replace with your bulk SMS gateway)
            $smsResponse = file_get_contents("https://api.yoursmsprovider.com/send?to=$recipientPhone&message=Your password reset code is $code");
            // You should ideally check the response
        }

        // Redirect to code verification form
        return $response
            ->withHeader('Location', '/verify-reset-code')
            ->withStatus(302);
    }



    //Log out
    public function logout(Request $request, Response $response, $args)
    {
        // Start the session and destroy it
        session_start();
        session_destroy();

        // Redirect to login page
        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }


}
