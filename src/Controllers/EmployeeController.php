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


class EmployeeController
{
    protected $view;
    //protected $db;

    public function __construct(Twig $view)
    {
        $this->view = $view;
        //$this->db = $db;
    }


    //Employees
    public function employees(Request $request, Response $response, $args){

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

        // Fetch all compliance records
        $employeeData = employees::all();

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
                'password'     => v::stringType()->length(6, null),
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

}