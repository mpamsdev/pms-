<?php

namespace App\Controllers;

use App\Models\companies;
use App\Models\compliance;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Carbon\Carbon;
use App\Models\employees;
use Illuminate\Pagination\Paginator;
use Ramsey\Uuid\Uuid;
use Respect\Validation\Validator as v;

class ComplianceController
{
    protected $view;
    //protected $db;

    public function __construct(Twig $view)
    {
        $this->view = $view;
        //$this->db = $db;
    }

    // Compliance Table View
    public function complianceTableView(Request $request, Response $response, $args)
    {
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
        $complianceData = Compliance::all();

        return $this->view->render($response, 'compliance-table.twig', [
            'title' => 'Compliance Table',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'compliance' => $complianceData,
        ]);
    }

    // Add Record
    public function addRecord(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            // Extract fields
            $certificate_name = trim($data['certificate_name'] ?? '');
            $renewal_date = trim($data['renewal_date'] ?? '');

            // Validation rules
            $validator = [
                'certificate_name' => v::notEmpty()->length(1, 100),
                'renewal_date' => v::notEmpty()->date('Y-m-d'),
            ];

            // Validate fields
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            if (!empty($errors)) {
                // Load existing data for the view
                $complianceData = compliance::all();
                return $this->view->render($response, 'compliance-table.twig', [
                    'title' => 'Compliance Table',
                    'errors' => $errors,
                    'compliance' => $complianceData,
                    'username' => $_SESSION['username'],
                    'userid' => $_SESSION['userid'],
                    'profile_picture' => $_SESSION['profile_picture'],
                    'userData' => $_SESSION['userData'],
                ]);
            }

            // Save record
            compliance::create([
                'certificate_name' => $certificate_name,
                'renewal_date' => $renewal_date,
            ]);

            // Fetch again after insertion
            $complianceData = Compliance::all();

            return $this->view->render($response, 'compliance-table.twig', [
                'title' => 'Compliance Table',
                'success' => 'Record added successfully.',
                'compliance' => $complianceData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }

        // Fallback GET
        return $response->withHeader('Location', '/dashboard/compliance-table')->withStatus(302);
    }

    //Update Record
    public function updateRecord(Request $request, Response $response, $args){
        session_start();

        $data = $request->getParsedBody();

        $certificate_name = trim($data['certificate_name'] ?? '');
        $renewal_date = trim($data['renewal_date'] ?? '');
        $id = trim($data['id']);

        // Validation rules
        $validator = [
            'certificate_name' => v::notEmpty()->length(1, 100),
            'renewal_date' => v::notEmpty()->date('Y-m-d'),
            'id' => v::notEmpty()->digit()
        ];

        // Validate fields
        $errors = [];
        foreach ($validator as $field => $rule) {
            if (!$rule->validate($$field)) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
            }
        }

        if (!empty($errors)) {
            // Load existing data for the view
            $complianceData = compliance::all();
            return $this->view->render($response, 'compliance-table.twig', [
                'title' => 'Compliance Table',
                'errors' => $errors,
                'compliance' => $complianceData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }

        //Update the record
        compliance::where('compliance_id', $id)->update([
            'certificate_name' => $certificate_name,
            'renewal_date' => $renewal_date,
        ]);


        // Fetch again after insertion
        $complianceData = Compliance::all();
        return $this->view->render($response, 'compliance-table.twig', [
            'title' => 'Compliance Table',
            'success' => 'Record updated successfully.',
            'compliance' => $complianceData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);

        // Fallback GET
        return $response->withHeader('Location', '/dashboard/compliance-table')->withStatus(302);
    }

    // Delete Compliance Records
    public function deleteRecord(Request $request, Response $response, $args) {
        session_start();
        $id = $args['id'];
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
            // Reload view with errors
            $complianceData = compliance::all();
            return $this->view->render($response, 'compliance-table.twig', [
                'title' => 'Compliance Table',
                'errors' => $errors,
                'compliance' => $complianceData,
                'username' => $_SESSION['username'],
                'userid' => $_SESSION['userid'],
                'profile_picture' => $_SESSION['profile_picture'],
                'userData' => $_SESSION['userData'],
            ]);
        }

        // Perform delete
        $deleted = compliance::where('compliance_id', $id)->delete();

        $message = $deleted ? 'Record deleted successfully.' : 'Record not found or already deleted.';

        // Reload compliance data after deletion
        $complianceData = compliance::all();
        return $this->view->render($response, 'compliance-table.twig', [
            'title' => 'Compliance Table',
            'success' => $message,
            'compliance' => $complianceData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);
    }




}
