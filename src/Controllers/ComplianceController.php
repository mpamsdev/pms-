<?php

namespace App\Controllers;

use App\Models\compliance;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Carbon\Carbon;
use App\Models\employees;
use Illuminate\Pagination\Paginator;
use Ramsey\Uuid\Uuid;
use Respect\Validation\Validator as v;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

        $success = $request->getQueryParams()['success'] ?? null;
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
            'success' => $success
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
            $create = compliance::create([
                'certificate_name' => $certificate_name,
                'renewal_date' => $renewal_date,
            ]);

            $message = $create ? 'New compliance record added successfully.' : 'Failed to add compliance.';

            // Redirect to GET /employees to prevent POST resubmission
            return $response
                ->withHeader('Location', '/compliance?success='.$message)
                ->withStatus(302);

        }


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

    //Update Record
    public function updateRecord(Request $request, Response $response, $args){
        session_start();

        if ($request->getMethod() === 'POST') {
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
            $update = compliance::where('compliance_id', $id)->update([
                'certificate_name' => $certificate_name,
                'renewal_date' => $renewal_date,
            ]);

            $message = $update ? 'Compliance record updated successfully.' : 'Failed to update compliance record.';

            // Redirect to GET /employees to prevent POST resubmission
            return $response
                ->withHeader('Location', '/compliance?success='.$message)
                ->withStatus(302);
        }

        // Fetch again after insertion
        $complianceData = Compliance::all();
        return $this->view->render($response, 'compliance-table.twig', [
            'title' => 'Compliance Table',
            'compliance' => $complianceData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);

    }

    // Delete Compliance Records
    public function deleteRecord(Request $request, Response $response, $args) {
        session_start();

        if ($request->getMethod() === 'POST'){
            $data = $request->getParsedBody();
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
            // Redirect to GET /employees to prevent POST resubmission
            return $response
                ->withHeader('Location', '/compliance?success='.$message)
                ->withStatus(302);
        }

        // Reload compliance data after deletion
        $complianceData = compliance::all();
        return $this->view->render($response, 'compliance-table.twig', [
            'title' => 'Compliance Table',
            //'success' => $message,
            'compliance' => $complianceData,
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);
    }


    //Compliance Check
    public function checkCompliance(Request $request, Response $response, $args)
    {
        $today = new \DateTime();
        $todayFormatted = $today->format('Y-m-d');
        $alertDays = [30, 15, 5];

        // Collect all matched records
        $recordsToAlert = collect();

        // 1. Expired or expiring today
        $expired = compliance::whereDate('renewal_date', '<=', $todayFormatted)->get();
        $recordsToAlert = $recordsToAlert->merge($expired);

        // 2. Upcoming expirations (30, 15, 5 days away)
        foreach ($alertDays as $days) {
            $targetDate = (clone $today)->modify("+$days days")->format('Y-m-d');
            $upcoming = compliance::whereDate('renewal_date', $targetDate)->get();
            $recordsToAlert = $recordsToAlert->merge($upcoming);
        }

        // Remove duplicates just in case
        $recordsToAlert = $recordsToAlert->unique('id');

        if ($recordsToAlert->isEmpty()) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'No compliance records found for today.'
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        // 3. Send email for each record
        $results = [];
        foreach ($recordsToAlert as $record) {
            $result = $this->sendEmail($record);

            $results[] = [
                'record_id' => $record->compliance_id,
                'title' => $record->title ?? $record->certificate_name ?? 'N/A',
                'status' => $result['status'],
                'message' => $result['message']
            ];
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => count($recordsToAlert) . ' compliance alerts processed.',
            'results' => $results
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }


    private function sendEmail($record)
    {
        try {
            $today = new \DateTime();
            $renewalDate = new \DateTime($record->renewal_date);

            // Determine status
            if ($renewalDate < $today) {
                $status = 'Expired';
                $subject = "Compliance Expired: {$record->certificate_name}";
                $message = "The compliance certificate <strong>{$record->title}</strong> expired on <strong>{$record->renewal_date}</strong>. Immediate action is required.";
            } elseif ($renewalDate->format('Y-m-d') === $today->format('Y-m-d')) {
                $status = 'Expires Today';
                $subject = "Compliance Expires Today: {$record->certificate_name}";
                $message = "The compliance certificate <strong>{$record->title}</strong> is due for renewal today (<strong>{$record->renewal_date}</strong>).";
            } else {
                $daysRemaining = $today->diff($renewalDate)->days;
                $status = "Expires in {$daysRemaining} days";
                $subject = "Compliance Renewal Reminder ({$daysRemaining} days): {$record->certificate_name}";
                $message = "The compliance certificate <strong>{$record->title}</strong> will expire on <strong>{$record->renewal_date}</strong> ({$daysRemaining} days remaining).";
            }

            // Send email
            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'fortresshubtechnologies.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['EMAIL'];
            $mail->Password = $_ENV['EMAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('support@fortresshubtechnologies.com', 'FortEdge HR System');
            $mail->addAddress('martine@fortresshubtechnologies.com', );
            //$mail->addCC('martine@fortresshubtechnologies.com');

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = '
            <div style="font-family: Arial, sans-serif; text-align: center; padding: 20px; background-color: #f4f4f4; color: #333;">
                <div style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);">
                    <img src="https://www.fortresshubtechnologies.com/wp-content/uploads/2024/08/cropped-cropped-cropped-FORTRESS-HUB-T-LOGO-2-250-x-250-px-2-e1724259446753.png" alt="Company Logo" style="width: 150px; margin-bottom: 20px;">
                    <h3 style="color: #333;">Compliance Alert</h3>
                    <p style="font-size: 16px; color: #555;">
                        ' . $message . '
                    </p>
                    <p style="font-size: 16px; color: #555;">
                        Best Regards,<br>
                        Fortress Hub Technologies Limited<br>
                        FortEdge HR System
                    </p>
                </div>
            </div>
        ';

            if ($mail->send()) {
                return ['status' => 'success', 'message' => "{$status} email sent successfully."];
            } else {
                return ['status' => 'failed', 'message' => $mail->ErrorInfo];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Failed to send reminder email: ' . $e->getMessage()];
        }
    }


}
