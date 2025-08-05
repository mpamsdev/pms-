<?php

namespace App\Controllers;

use App\Models\companies;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Models\employee;
use Respect\Validation\Validator as v;


class AccountController
{
    protected Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    // Success: Personal Details
    public function success(Request $request, Response $response, $args)
    {

        return $this->view->render($response, 'account/success.twig', [
            'title' => "Success",
        ]);
    }
    // Step 1: Personal Details
    public function stepOne(Request $request, Response $response, $args)
    {
        session_start();
        $data = $request->getParsedBody();

        //var_dump($data);

        if ($request->getMethod() === 'POST') {
            $_SESSION['registration']['step1'] = $data;
            return $response->withHeader('Location', '/account/ind/step-two')->withStatus(302);
        }

        return $this->view->render($response, 'account/registration.twig', [
            'title' => "Step One",
            'pageName' => "Personal Details",
            'formData' => $_SESSION['registration']['step1'] ?? []
        ]);
    }

    // Step 2: Occupational Details
    public function stepTwo(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $_SESSION['registration']['step2'] = $data;

            return $response->withHeader('Location', '/account/ind/step-three')->withStatus(302);
        }

        return $this->view->render($response, 'account/registration_step2.twig', [
            'title' => "Step Two",
            'pageName' => "Occupational Details",
            'formData' => $_SESSION['registration']['step2'] ?? []
        ]);
    }

    // Step 3: Next of Kin Details
    public function stepThree(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $_SESSION['registration']['step3'] = $data;

            return $response->withHeader('Location', '/account/ind/step-four')->withStatus(302);
        }

        return $this->view->render($response, 'account/registration_step3.twig', [
            'title' => "Step Three",
            'pageName' => "Next of Kin Details",
            'formData' => $_SESSION['registration']['step3'] ?? []
        ]);
    }

    // Step 4: Banking Details
    public function stepFour(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $_SESSION['registration']['step4'] = $data;

            return $response->withHeader('Location', '/account/ind/step-five')->withStatus(302);
        }

        return $this->view->render($response, 'account/registration_step4.twig', [
            'title' => "Step Four",
            'pageName' => "Banking Details",
            'formData' => $_SESSION['registration']['step4'] ?? []
        ]);
    }

    // Step 5: Passport Size Photo Upload
    public function stepFive(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();

            if (isset($body['profile-picture'])) {
                $dataUrl = $body['profile-picture'];

                // Remove the data:image/png;base64, part
                $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);

                // Validate base64 string (optional)
                if (base64_decode($base64, true)) {
                    $_SESSION['registration']['step5']['profile-picture'] = $base64;

                    return $response
                        ->withHeader('Location', '/account/ind/complete-registration')
                        ->withStatus(302);
                }
            }

            // fallback in case of error
            return $this->view->render($response, 'account/registration_step5.twig', [
                'title' => "Step Five",
                'pageName' => "Passport Size Photo",
                'formData' => $_SESSION['registration']['step5'] ?? [],
                'error' => 'Invalid or missing photo.'
            ]);
        }

        return $this->view->render($response, 'account/registration_step5.twig', [
            'title' => "Step Five",
            'pageName' => "Passport Size Photo",
            'formData' => $_SESSION['registration']['step5'] ?? []
        ]);
    }


    // Step 6: Create Password & Complete Registration
    public function completeRegistration(Request $request, Response $response, $args) {
        $session = $_SESSION['registration'] ?? [];

        // Redirect to start if session is missing
        if (empty($session)) {
            return $response->withHeader('Location', '/account/ind/step-one')->withStatus(302);
        }

        // Show password creation form on GET
        if ($request->getMethod() === 'GET') {
            return $this->view->render($response, 'account/registration_step6.twig', [
                'title' => 'Create Password',
                'pageName' => 'Final Step',
            ]);
        }

        // POST: Continue with user creation
        $data = $request->getParsedBody();

        // Combine all step data
        $allData = array_merge(
            $session['step1'] ?? [],
            $session['step2'] ?? [],
            $session['step3'] ?? [],
            $session['step4'] ?? [],
            $session['step5'] ?? [],
            isset($data['password']) ? ['password' => $data['password']] : []
        );

        // Validate required fields
        $validator = v::key('username', v::email())
            ->key('password', v::stringType()->length(6, null))
            ->key('firstname', v::regex('/^[A-Za-z\s\-]+$/'))
            ->key('lastname', v::regex('/^[A-Za-z\s\-]+$/'))
            ->key('nationalCard', v::stringType())
            ->key('dob', v::date())
            ->key('gender', v::in(['Male', 'Female']))
            ->key('phone', v::phone())
            ->key('title', v::stringType());
        try {
            $validator->assert($allData);
        } catch (\Respect\Validation\Exceptions\NestedValidationException $e) {
            return $this->view->render($response, 'account/registration_step6.twig', [
                'title' => 'Create Password',
                'pageName' => 'Final Step',
                'formData' => $data,
                'errors' => $e->getMessages()
            ]);
        }

        // Check if username exists
        $existingUser = employee::where('username', $allData['username'])->first();
        if ($existingUser) {
            return $this->view->render($response, 'account/registration_step6.twig', [
                'title' => 'Create Password',
                'pageName' => 'Final Step',
                'formData' => $data,
                'errors' => ['username' => 'Username already taken.']
            ]);
        }

        // Generate new userid
        $lastUser = employee::orderByDesc('userid')->first();
        $newUserid = $lastUser ? $lastUser->userid + 1 : 1000;

        $photoBase64 = $session['step5']['profile-picture'] ?? null;
        $filename = null;

        if ($photoBase64) {
            $filename = uniqid('photo_') . '.png';
            $uploadDir = dirname(__DIR__, 2) . '/public/assets/uploads/profiles';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!is_writable($uploadDir)) {
                throw new \Exception("Upload folder not writable: $uploadDir");
            }

            $path = $uploadDir . '/' . $filename;
            $saved = file_put_contents($path, base64_decode($photoBase64));

            if ($saved === false) {
                throw new \Exception("Failed to write image to $path");
            }
        }


        $step1 = $_SESSION['registration']['step1'] ?? [];

        // Create user
        $user = employee::create([
            'userid' => $newUserid,
            'company_id' => null,
            'username' => $step1['username'],
            'password' => password_hash($allData['password'], PASSWORD_BCRYPT),
            'verificationToken' => bin2hex(random_bytes(16)),
            'title' => $step1['title'],
            'firstname' => $step1['firstname'],
            'lastname' => $step1['lastname'],
            'phone' => $step1['phone'],
            'nationalCard' => $step1['nationalCard'],
            'dob' => $step1['dob'],
            'gender' => $step1['gender'],
            'address' => $step1['homeAddress'],
            'city' => $step1['city'],
            'country' => $step1['country'],
            'is_verified' => 0,
            'profile_picture' => $filename
        ]);

        $step2 = $_SESSION['registration']['step2'] ?? [];
        // Save occupation
        \App\Models\occupation::create([
            'userid' => $newUserid,
            'workStatus' => $step2['work-status'],
            'position' => $step2['position'],
            'department' => $step2['department'],
            'company' => $step2['company-name'],
            'address' => $step2['company-address'],
        ]);

        $step3 = $_SESSION['registration']['step3'] ?? [];
        // Save next of kin
        \App\Models\next_of_kin::create([
            'userid' => $newUserid,
            'firstname' => $step3['firstname'],
            'lastname' => $step3['lastname'],
            'next_phone' => $step3['phone'],
            'next_national_id' => $step3['nationalCard'],
            'next_relationship' => $step3['relationship'],
            'next_address' => $step3['homeAddress'],
            'next_work_status' => $step3['employment-status'],
            'next_company' => $step3['company'],
            'next_company_address' => $step3['company-address'],
        ]);

        $step4 = $_SESSION['registration']['step4'] ?? [];
        //Save bank Details
        \App\Models\bank_details::created([
            'userid' => $newUserid,
            'account_number' => $step4['bank-account-number'],
            'bank_name' => $step4['bank-name'],
            'branch_name' => $step4['branch-name'],
        ]);

        unset($_SESSION['registration']); // Clear session

        return $response
            ->withHeader('Location', '/success?message=' . urlencode('Your account has been created.'))
            ->withStatus(302);
//        return $this->view->render($response, 'auth/login.twig', [
//            'title' => 'Registration Complete',
//            'message' => 'Your account has been successfully created.'
//        ]);
    }


    //Organisation Registration
    public function stepOneOrg(Request $request, Response $response, $args)
    {
        session_start();

        $data = $request->getParsedBody();

        //var_dump($data);
        if ($request->getMethod() === 'POST') {
            $_SESSION['organisation_registration']['stepOne'] = $data;
            return $response->withHeader('Location', '/account/org/step-two')->withStatus(302);
        }

        return $this->view->render($response, 'account/company_registration.twig', [
            'title' => "Step One",
            'pageName' => "Organisational Details",
            'formData' => $_SESSION['organisation_registration']['stepOne'] ?? []
        ]);
    }

    //Organisation Registration
    public function stepTwoOrg(Request $request, Response $response, $args)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($request->getMethod() === 'POST') {
            //$parsedBody = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            $uploadDir = dirname(__DIR__, 2). '/public/assets/uploads/tmp/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileNames = [];

            foreach (['tcc', 'prc', 'pf3'] as $field) {
                if (isset($uploadedFiles[$field])) {
                    $file = $uploadedFiles[$field];

                    if ($file->getError() === UPLOAD_ERR_OK) {
                        $originalName = $file->getClientFilename();
                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        $safeName = uniqid($field . '_') . '.' . $extension;
                        $file->moveTo($uploadDir . $safeName);

                        $fileNames[$field] = $safeName;
                    }
                }
            }

            $_SESSION['organisation_registration']['stepTwo'] = $fileNames;

            return $response->withHeader('Location', '/account/org/step-three')->withStatus(302);
        }

        return $this->view->render($response, 'account/company_registration_step2.twig', [
            'title' => "Step Two",
            'pageName' => "Company Documents",
            'formData' => $_SESSION['organisation_registration']['stepTwo'] ?? []
        ]);
    }



    public function stepThreeOrg(Request $request, Response $response, $args)
    {
        //session_start();

        $session = $_SESSION['organisation_registration'] ?? [];

        if (empty($session)) {
            return $response->withHeader('Location', '/account/org/step-one')->withStatus(302);
        }

        if ($request->getMethod() === 'GET') {
            return $this->view->render($response, 'account/company_registration_step3.twig', [
                'title' => 'Create Password',
                'pageName' => 'Final Step',
            ]);
        }

        $data = $request->getParsedBody();

        $allData = array_merge(
            $session['stepOne'] ?? [],
            $session['stepTwo'] ?? [],
            isset($data['password']) ? ['password' => $data['password']] : []
        );

        // Validation
        $validator = v::key('username', v::email())
            ->key('password', v::stringType()->length(6, null))
            ->key('company', v::regex('/^[A-Za-z\s\-]+$/'))
            ->key('registration_number', v::digit()->length(4, 20))
            ->key('homeAddress', v::stringType())
            ->key('operation_area', v::regex('/^[A-Za-z\s\-]+$/'))
            ->key('phone', v::phone())
            ->key('tcc', v::stringType())
            ->key('prc', v::stringType())
            ->key('pf3', v::stringType());

        try {
            $validator->assert($allData);
        } catch (\Respect\Validation\Exceptions\NestedValidationException $e) {
            return $this->view->render($response, 'account/company_registration_step3.twig', [
                'title' => 'Create Password',
                'pageName' => 'Final Step',
                'formData' => $data,
                'errors' => $e->getMessages()
            ]);
        }

        // Check for existing username
        $existingUser = companies::where('username', $allData['username'])->first();
        if ($existingUser) {
            return $this->view->render($response, 'account/company_registration_step3.twig', [
                'title' => 'Create Password',
                'pageName' => 'Final Step',
                'formData' => $data,
                'errors' => ['username' => 'Username already taken.']
            ]);
        }

        // Handle file move
        $uploadDir = dirname(__DIR__, 2). '/public/assets/uploads/org/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $finalFiles = [];

        foreach (['tcc', 'prc', 'pf3'] as $field) {
            $tempFileName = $allData[$field];
            $tempFilePath = dirname(__DIR__ , 2) . '/public/assets/uploads/tmp/' . $tempFileName;

            if (file_exists($tempFilePath)) {
                $newFileName = uniqid($field . '_') . '.' . pathinfo($tempFileName, PATHINFO_EXTENSION);
                $finalPath = $uploadDir . $newFileName;

                if (rename($tempFilePath, $finalPath)) {
                    $finalFiles[$field] = $newFileName;
                } else {
                    return $this->view->render($response, 'account/company_registration_step3.twig', [
                        'title' => 'Create Password',
                        'pageName' => 'Final Step',
                        'formData' => $data,
                        'errors' => ["$field" => "Failed to move uploaded $field file."]
                    ]);
                }
            }
        }

        //Save to the Database
        \App\Models\companies::create([
            'company_number' => $allData['registration_number'],
            'username' => $allData['username'],
            'phone' => $allData['phone'],
            'company_name' => $allData['company'],
            'operation_area' => $allData['operation_area'],
            'year_of_operation' => $allData['years'],
            'password' => password_hash($allData['password'], PASSWORD_DEFAULT),
            'country' => $allData['country'],
            'city' => $allData['city'],
            'address' => $allData['homeAddress'],
            'tax_clearance_cert' => $finalFiles['tcc'] ?? null,
            'company_reg_cert' => $finalFiles['prc'] ?? null,
            'form_three_cert' => $finalFiles['pf3'] ?? null,
        ]);

        // Clean up session
        unset($_SESSION['organisation_registration']);

        return $response
            ->withHeader('Location', '/success?message=' . urlencode('Your account has been created.'))
            ->withStatus(302);
    }

}
