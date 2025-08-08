<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Carbon\Carbon;
use App\Models\employees;
use Illuminate\Pagination\Paginator;
use Ramsey\Uuid\Uuid;
use Respect\Validation\Validator as v;
use DateTime;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



class EmployeeController
{
    protected $view;
    //protected $db;

    public function __construct(Twig $view)
    {
        $this->view = $view;
        //$this->db = $db;
    }


    //Employee
    public function employees(Request $request, Response $response, $args) {
        session_start();

        // Auth check
        if (!isset($_SESSION['userid']) || !isset($_SESSION['username'])) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        // Session expiry check
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $_SESSION['session_timeout']) {
            session_unset();
            session_destroy();
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        // Fetch all employees
        $employeeData = employees::all();
        $now = new DateTime();

        // Loop over $employeeData, not $employees
        foreach ($employeeData as &$employee) {
            if (!empty($employee->contract_start_date) && !empty($employee->contract_end_date)) {
                $start = new DateTime($employee->contract_start_date);
                $end = new DateTime($employee->contract_end_date);

                if ($now > $end) {
                    $progress = "Expired";
                } else {
                    $passedInterval = $start->diff($now);
                    $monthsPassed = $passedInterval->m + ($passedInterval->y * 12);
                    $daysPassed = $passedInterval->d;

                    if ($monthsPassed == 0) {
                        // Less than one month
                        $progress = $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                    } else {
                        // One or more months
                        $progress = $monthsPassed . ' month' . ($monthsPassed > 1 ? 's' : '');
                        if ($daysPassed > 0) {
                            $progress .= ', ' . $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                        }
                    }
                }
            } else {
                $progress = '-';
            }
            $employee->contract_progress = $progress;
        }
        unset($employee); // break the reference

        return $this->view->render($response, 'employees.twig', [
            'title' => 'Employees',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'employees' => $employeeData,
        ]);
    }


    // Add an Employee
    public function addEmployee(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            // Validation rules
            $validator = [
                'name' => v::notEmpty()->length(1, 100),
                'username' => v::notEmpty()->email(),
                'phone' => v::notEmpty()->phone(),
                'department' => v::notEmpty(),
                'job_title' => v::notEmpty(),
                'contract_type' => v::notEmpty(),
                'national_id' => v::notEmpty(),
                'gender' => v::notEmpty(),
                'tax_id' => v::notEmpty(),
                'ssn' => v::notEmpty(),
                'nhima_number' => v::notEmpty(),
                'password'     => v::stringType()->length(6, null)
            ];

            // Validate fields
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($data[$field] ?? null)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            if (!empty($data['contract_start_date']) && !v::date()->validate($data['contract_start_date'])) {
                $errors['contract_start_date'] = 'Start date is invalid.';
            }
            if (!empty($data['contract_end_date']) && !v::date()->validate($data['contract_end_date'])) {
                $errors['contract_end_date'] = 'End date is invalid.';
            }

            if (!empty($errors)) {
                $employeeData = employees::all();
                return $this->view->render($response, 'employees.twig', [
                    'title' => 'Employees',
                    'errors' => $errors,
                    'employees' => $employeeData,
                    'username' => $_SESSION['username'],
                    'userid' => $_SESSION['userid'],
                    'profile_picture' => $_SESSION['profile_picture'],
                    'userData' => $_SESSION['userData'],
                ]);
            }

            // Save employee record
            employees::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'],
                'department' => $data['department'],
                'job_title' => $data['job_title'],
                'contract_type' => $data['contract_type'],
                'contract_start_date' => $data['contract_start_date'] ?? null,
                'contract_end_date' => $data['contract_end_date'] ?? null,
                'gender' => $data['gender'],
                'national_id' => $data['national_id'],
                'tax_id' => $data['tax_id'],
                'ssn' => $data['ssn'],
                'nhima_number' => $data['nhima_number'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT)
            ]);

            // Fetch again after insertion
            $employeeData = employees::all();
            $now = new DateTime();

            // Loop over $employeeData, not $employees
            foreach ($employeeData as &$employee) {
                if (!empty($employee->contract_start_date) && !empty($employee->contract_end_date)) {
                    $start = new DateTime($employee->contract_start_date);
                    $end = new DateTime($employee->contract_end_date);

                    if ($now > $end) {
                        $progress = "Expired";
                    } else {
                        $passedInterval = $start->diff($now);
                        $monthsPassed = $passedInterval->m + ($passedInterval->y * 12);
                        $daysPassed = $passedInterval->d;

                        if ($monthsPassed == 0) {
                            // Less than one month
                            $progress = $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                        } else {
                            // One or more months
                            $progress = $monthsPassed . ' month' . ($monthsPassed > 1 ? 's' : '');
                            if ($daysPassed > 0) {
                                $progress .= ', ' . $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                            }
                        }
                    }
                } else {
                    $progress = '-';
                }
                $employee->contract_progress = $progress;
            }
            unset($employee); // break the reference

            return $this->view->render($response, 'employees.twig', [
                'title' => 'Employees',
                'success' => 'Employee added successfully.',
                'employees' => $employeeData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }

        // GET Request - Load all employees
        $employeeData = employees::all();

        return $this->view->render($response, 'employees.twig', [
            'title' => 'Employees',
            'employees' => $employeeData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);
    }

    //Update employee records
    public function updateEmployee(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            // Validation rules
            $validator = [
                'name' => v::notEmpty()->length(1, 100),
                'username' => v::notEmpty()->email(),
                'phone' => v::notEmpty()->phone(),
                'department' => v::notEmpty(),
                'job_title' => v::notEmpty(),
                'contract_type' => v::notEmpty(),
                'national_id' => v::notEmpty(),
                'gender' => v::notEmpty(),
                'tax_id' => v::notEmpty(),
                'ssn' => v::notEmpty(),
                'nhima_number' => v::notEmpty(),
                //'password'     => v::stringType()->length(6, null),
                'id' => v::notEmpty()->digit()
            ];

            // Validate fields
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($data[$field] ?? null)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            if (!empty($data['contract_start_date']) && !v::date()->validate($data['contract_start_date'])) {
                $errors['contract_start_date'] = 'Start date is invalid.';
            }
            if (!empty($data['contract_end_date']) && !v::date()->validate($data['contract_end_date'])) {
                $errors['contract_end_date'] = 'End date is invalid.';
            }

            if (!empty($errors)) {
                $employeeData = employees::all();
                return $this->view->render($response, 'employees.twig', [
                    'title' => 'Employees',
                    'errors' => $errors,
                    'employees' => $employeeData,
                    'username' => $_SESSION['username'],
                    'userid' => $_SESSION['userid'],
                    'profile_picture' => $_SESSION['profile_picture'],
                    'userData' => $_SESSION['userData'],
                ]);
            }

            // Save employee record
            employees:: where('id', $data['id'])->update([
                'name' => $data['name'] ?? '',
                'username' => $data['username'] ?? '',
                'phone' => $data['phone'],
                'department' => $data['department'] ?? '',
                'job_title' => $data['job_title'] ?? '',
                'contract_type' => $data['contract_type'] ?? '',
                'contract_start_date' => $data['contract_start_date'] ?? null,
                'contract_end_date' => $data['contract_end_date'] ?? null,
                'gender' => $data['gender'] ?? '',
                'national_id' => $data['national_id'] ?? '',
                'tax_id' => $data['tax_id'] ?? '',
                'ssn' => $data['ssn'] ?? '',
                'nhima_number' => $data['nhima_number'] ?? '',
                'password' => password_hash($data['password'] ?? '', PASSWORD_DEFAULT)
            ]);

            // Fetch again after insertion
            $employeeData = employees::all();
            $now = new DateTime();

            // Loop over $employeeData, not $employees
            foreach ($employeeData as &$employee) {
                if (!empty($employee->contract_start_date) && !empty($employee->contract_end_date)) {
                    $start = new DateTime($employee->contract_start_date);
                    $end = new DateTime($employee->contract_end_date);

                    if ($now > $end) {
                        $progress = "Expired";
                    } else {
                        $passedInterval = $start->diff($now);
                        $monthsPassed = $passedInterval->m + ($passedInterval->y * 12);
                        $daysPassed = $passedInterval->d;

                        if ($monthsPassed == 0) {
                            // Less than one month
                            $progress = $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                        } else {
                            // One or more months
                            $progress = $monthsPassed . ' month' . ($monthsPassed > 1 ? 's' : '');
                            if ($daysPassed > 0) {
                                $progress .= ', ' . $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                            }
                        }
                    }
                } else {
                    $progress = '-';
                }
                $employee->contract_progress = $progress;
            }
            unset($employee); // break the reference

            return $this->view->render($response, 'employees.twig', [
                'title' => 'Employees',
                'success' => 'Employee record updated successfully.',
                'employees' => $employeeData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }

        // GET Request - Load all employees
        $employeeData = employees::all();

        return $this->view->render($response, 'employees.twig', [
            'title' => 'Employees',
            'employees' => $employeeData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);
    }

    //Delete Employee
    public function deleteEmployee(Request $request, Response $response, $args)
    {
        session_start();
        $data = $request->getParsedBody();

        //var_dump($data);
        $id = $data['id'];
        // Validation rules
        $validator = [
            'id' => v::notEmpty()->digit()
        ];

        // Validate ID
        $errors = [];
        foreach ($validator as $field => $rule) {
            if (!$rule->validate($$field)) {
                $errors[$field] = ucfirst($field) . ' is required or invalid.';
            }
        }

        if (!empty($errors)) {
            $employeeData = employees::all();
            return $this->view->render($response, 'employees.twig', [
                'title' => 'Employees',
                'errors' => $errors,
                'employees' => $employeeData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }

        // Perform delete
        $deleted = employees::where('id', $id)->delete();

        $message = $deleted ? 'Record deleted successfully.' : 'Record not found or already deleted.';

        // Fetch again after insertion
        $employeeData = employees::all();
        $now = new DateTime();

        // Loop over $employeeData, not $employees
        foreach ($employeeData as &$employee) {
            if (!empty($employee->contract_start_date) && !empty($employee->contract_end_date)) {
                $start = new DateTime($employee->contract_start_date);
                $end = new DateTime($employee->contract_end_date);

                if ($now > $end) {
                    $progress = "Expired";
                } else {
                    $passedInterval = $start->diff($now);
                    $monthsPassed = $passedInterval->m + ($passedInterval->y * 12);
                    $daysPassed = $passedInterval->d;

                    if ($monthsPassed == 0) {
                        // Less than one month
                        $progress = $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                    } else {
                        // One or more months
                        $progress = $monthsPassed . ' month' . ($monthsPassed > 1 ? 's' : '');
                        if ($daysPassed > 0) {
                            $progress .= ', ' . $daysPassed . ' day' . ($daysPassed > 1 ? 's' : '');
                        }
                    }
                }
            } else {
                $progress = '-';
            }
            $employee->contract_progress = $progress;
        }
        unset($employee); // break the reference

        return $this->view->render($response, 'employees.twig', [
            'title' => 'Employees',
            'success' => $message,
            'employees' => $employeeData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);

    }

    //Suspend Employee
    public function suspendEmployee(Request $request, Response $response, $args)
    {
        session_start();

        $data = $request->getParsedBody();

        //var_dump($data);
        $id = $data['id'];

        // Validation rules
        $validator = [
            'id' => v::notEmpty()->digit()
        ];

        // Validate ID
        $errors = [];
        foreach ($validator as $field => $rule) {
            if (!$rule->validate($$field)) {
                $errors[$field] = ucfirst($field) . ' is required or invalid.';
            }
        }

        if (!empty($errors)) {
            $employeeData = employees::all();
            return $this->view->render($response, 'employees.twig', [
                'title' => 'Employees',
                'errors' => $errors,
                'employees' => $employeeData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }


        // Perform suspension
        $suspend = employees::where('id', $id)->update(['status' => 'suspended']);

        $message = $suspend ? 'Employee suspended successfully.' : 'Record not found or already deleted.';

        // Fetch again after insertion
        $employeeData = employees::all();

        return $this->view->render($response, 'employees.twig', [
            'title' => 'Employees',
            'success' => $message,
            'employees' => $employeeData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);

    }

    //Activate Employee
    public function activateEmployee(Request $request, Response $response, $args)
    {
        session_start();

        $data = $request->getParsedBody();

        //var_dump($data);
        $id = $data['id'];

        // Validation rules
        $validator = [
            'id' => v::notEmpty()->digit()
        ];

        // Validate ID
        $errors = [];
        foreach ($validator as $field => $rule) {
            if (!$rule->validate($$field)) {
                $errors[$field] = ucfirst($field) . ' is required or invalid.';
            }
        }

        if (!empty($errors)) {
            $employeeData = employees::all();
            return $this->view->render($response, 'employees.twig', [
                'title' => 'Employees',
                'errors' => $errors,
                'employees' => $employeeData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }


        // Perform suspension
        $suspend = employees::where('id', $id)->update(['status' => 'active']);

        $message = $suspend ? 'Employee activated successfully.' : 'Record not found or already deleted.';

        // Fetch again after insertion
        $employeeData = employees::all();

        return $this->view->render($response, 'employees.twig', [
            'title' => 'Employees',
            'success' => $message,
            'employees' => $employeeData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);

    }


    private function sendEmail($email, $password)
    {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail = new PHPMailer();
            $mail->isSMTP();  // Enable SMTP
            $mail->Host = 'fortresshubtechnologies.com';  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;   // Enable SMTP authentication

            // SMTP username and password
            $mail->Username = 'training@fortresshubtechnologies.com';
            $mail->Password = 'Fht@2025!'; // Ensure this is securely managed

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
            $mail->Port = 587; // TCP port for TLS

            // Recipients and Sender
            $mail->setFrom('training@fortresshubtechnologies.com', 'Fortress Hub Technologies Limited');
            $mail->addAddress('lweendo@fortresshubtechnologies.com'); // Add recipient
            $mail->addCC('martine@fortresshubtechnologies.com');

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Compliance Renewal Reminder: {$record->certificate_name}";
            // Email body content with logo, button, and contact details
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; text-align: center; padding: 20px; background-color: #f4f4f4; color: #333;">
                    <div style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);">
                       <img src="https://www.fortresshubtechnologies.com/wp-content/uploads/2024/08/cropped-cropped-cropped-FORTRESS-HUB-T-LOGO-2-250-x-250-px-2-e1724259446753.png" alt="Company Logo" style="width: 150px; margin-bottom: 20px;">
                        <h3 style="color: #333;">Compliance Reminder</h3>
                        <p style="font-size: 16px; color: #555;">
                            <span style="font-weight: bold;">
                             Hello, 
                            </span>
                        </p>
                        <p style="font-size: 16px; color: #555;">
                            
                        </p>
                        <p style="font-size: 16px; color: #555;">Here are the details of your registration:</p>
                        
                        <p style="font-size: 16px; color: #555; ">
                            This bootcamp is designed to provide you with cutting-edge knowledge and hands-on experience in 
                            AI and Cybersecurity.
                        </p>
                       
                        <p>If you need any further assistance, Please raise a ticket to:</p>
                        <p>Email: <a href="mailto:support@fortresshubtechnologies.com" style="color: #007bff;">support@fortresshubtechnologies.com</a><br>
                           Phone: <a href="tel:+260965249614" style="color: #007bff;">+260965249614</a>
                        </p>
                        <p style="font-size: 16px; color: #555;">
                            Best Regards,<br>
                            Fortress Hub Technologies Limited
                            <br>
                            FortEdge HR 
                        </p>
                       
                    </div>
                    
                </div>
                ';
            $mail->send();
        } catch (Exception $e) {
            throw new Exception('Failed to send  : ' . $e->getMessage());
        }
    }

}