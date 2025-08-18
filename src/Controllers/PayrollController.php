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
use App\Models\salary;
use App\Models\allowance;
use App\Models\deductions;
use App\Models\payroll;
use Mpdf\Mpdf;

class PayrollController{

    protected $view;
    //protected $db;

    public function __construct(Twig $view)
    {
        $this->view = $view;
        //$this->db = $db;
    }

    //Payroll

    public function index(Request $request, Response $response, $args){
        session_start();
        function getFlashMessages(array $queryParams): array
        {
            $types = ['allowance', 'deduction', 'salary'];
            $messages = [];

            foreach ($types as $type) {
                // Errors
                $errorKey = $type . 'Errors';
                $messages[$errorKey] = [];

                if (!empty($queryParams[$errorKey])) {
                    $decoded = json_decode($queryParams[$errorKey], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $messages[$errorKey] = $decoded;
                    }
                }

                // Success
                $successKey = $type . 'Success';
                $messages[$successKey] = $queryParams[$successKey] ?? null;
            }

            // Generic success message
            $messages['success'] = $queryParams['success'] ?? null;

            return $messages;
        }
        if (!isset($_SESSION['userid']) || !isset($_SESSION['username'])) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        // Session expiry check
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $_SESSION['session_timeout']) {
            session_unset();
            session_destroy();
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $queryParams = $request->getQueryParams();
        $flashMessages = getFlashMessages($queryParams);


        //$employeeData = employees::all();
        // Only employees with salaries
        $employeesWithSalaries = employees::whereIn('id', function ($query) {
            $query->select('employee_id')->from('salaries');
        })->get();

        $payrolls = payroll::all();
        return $this->view->render($response, 'payroll.twig', [
            'title' => 'Payroll Management',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'employees' => $employeesWithSalaries,
            'payrolls' => $payrolls,
            'success' => $flashMessages['success'],
            'errors' => $flashMessages['errors'],
        ]);
    }

    public function addPayroll(Request $request, Response $response, $args){
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $employee_id = trim($data['employee_id'] ?? '');
            $account_number = trim($data['account_number'] ?? '');
            $bank_name = trim($data['bank_name'] ?? '');
            $branch_name = trim($data['branch_name'] ?? '');

            // Validation rules
            $validator = [
                'employee_id' => v::notEmpty()->digit(),
                'account_number' => v::digit()->notEmpty(),
                'bank_name' => v::stringType()->notEmpty(),
                'branch_name' => v::stringType()->notEmpty(),
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // Check if salary exists
            if ($employee_id && payroll::where('employee_id', $employee_id)->exists()) {
                $errors[] = 'A payroll record for this employee already exists.';
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                //$payload = ['errors' => $errors];
                // Redirect back with errors
                $query = http_build_query([
                    'errors' => implode(', ', $errors)
                ]);
                return $response
                    ->withHeader('Location', '/payroll?' . $query)
                    ->withStatus(302);
            }

            $net_pay = salary::where('employee_id', $employee_id)->value('net_pay');
            // Fetch salary details
            $basic_pay = salary::where('employee_id', $employee_id)->value('basic_salary') ?? 0;
            // Fetch allowances and deductions
            $allowances = allowance::where('employee_id', $employee_id)->get();
            $deductions = deductions::where('employee_id', $employee_id)->get();

            $total_allowances = $allowances->sum('amount');
            $total_deductions = $deductions->sum('amount');

            $gross_pay = $basic_pay + $total_allowances;
            $net_pay   = $gross_pay - $total_deductions;
            // Save record
            $create = payroll::create([
                'employee_id' => $employee_id,
                'account_number' => $account_number,
                'bank_name' => $bank_name,
                'branch_name' => $branch_name,
                'net_pay' => $net_pay
            ]);

            if ($create) {
                // Fetch employee

                // ----------------------------
                // Generate payslip with mPDF
                // ----------------------------

                $payslip_no = "FHT-" . str_pad($create->employee_id, 5, '0', STR_PAD_LEFT);

                $employee = employees::find($employee_id);
                $basic_pay = Salary::where('employee_id', $employee_id)->value('basic_salary');
                $allowances = allowance::where('employee_id', $employee_id)->get();
                $deductions = deductions::where('employee_id', $employee_id)->get();

                $currentMonth = date('F'); // Returns full month name, e.g., "August"

                $gross_pay = $basic_pay;
                foreach ($allowances as $a) $gross_pay += $a->amount;

                $total_deductions = 0;
                foreach ($deductions as $d) $total_deductions += $d->amount;

                $net_pay = $gross_pay - $total_deductions;

// Build allowances HTML
                $allowances_html = "";
                foreach ($allowances as $a) {
                    $allowances_html .= "<tr><td>{$a->type}</td><td align='right'>" . number_format($a->amount, 2) . "</td><td>-</td></tr>";
                }

// Build deductions HTML
                $deductions_html = "";
                foreach ($deductions as $d) {
                    $deductions_html .= "<tr><td>{$d->type}</td><td>-</td><td align='right'>" . number_format($d->amount, 2) . "</td></tr>";
                }


                $html = "
<div style='text-align:center; margin-top: 20rem; font-family: \"Olswald\", sans-serif;'>
    <img src='/public/assets/img/fht.png' height='120' alt='Company Logo'>
    <h5>
        Synod Complex, Mosi-oa-Tunya Road, <br>
        Woodlands, Lusaka
    </h5>
    <hr style='border:3px solid #000; width: 50%'>
    <h2>E-Payslip</h2>
    <h3>{$employee->name}</h3>
</div>

<!-- Header Info in single row -->
<div style='font-size:11px; margin-bottom:3em; font-family: \"Olswald\", sans-serif;'>
    <table width='100%' style='border-collapse:collapse;'>
        <tr>
            <td><strong>Date:</strong> " . date('d M Y') . "</td>
            <td><strong>Bank:</strong> {$bank_name}</td>
            <td><strong>Payslip #:</strong> {$payslip_no}</td>
            <td><strong>Month:</strong> {$currentMonth}</td>
        </tr>
    </table>
</div>

<table width='100%' border='1' cellspacing='0' cellpadding='5' style='border-collapse:collapse; font-size:12px; font-family: \"Olswald\", sans-serif;'>
    <tr>
        <th>Item / Description</th>
        <th>Allowances</th>
        <th>Deductions</th>
    </tr>
    <tr>
        <td>Basic Pay</td>
        <td align='right'>" . number_format($basic_pay, 2) . "</td>
        <td>-</td>
    </tr>
    {$allowances_html}
    {$deductions_html}
    <tr>
        <td><strong>Gross Pay</strong></td>
        <td colspan='2' align='right'><strong>" . number_format($gross_pay, 2) . "</strong></td>
    </tr>
    <tr>
        <td><strong>Net Pay</strong></td>
        <td colspan='2' align='right'><strong>" . number_format($net_pay, 2) . "</strong></td>
    </tr>
</table>
<p>Total Net Payable:ZMW" . number_format($net_pay, 2) . " </p>
<!-- Footer horizontal line -->
<hr style='border:3px solid #000; margin-top:2em;'>
<div style='text-align:center; font-family: \"Olswald\", sans-serif; font-size:10px;'>
    <p>This is a system-generated payslip. Kindly report any under/over payments</p>
</div>
";


// Create mPDF instance
                $baseDir = dirname(__DIR__, 2);

// Define temp and payslip dirs
                $tempDir = $baseDir . '/public/assets/uploads/tmp';
                $payslipDir = $baseDir . '/public/assets/uploads/payslips/';


// Absolute logo path for watermark
                $logoPath = $baseDir . '/public/assets/img/fht.png';
// Ensure directories exist
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0777, true);
                }
                if (!is_dir($payslipDir)) {
                    mkdir($payslipDir, 0777, true);
                }

// Create mPDF instance with writable tempDir
                $mpdf = new Mpdf(['tempDir' => $tempDir]);

// ✅ Add watermark logo
                if (file_exists($logoPath)) {
                    $mpdf->SetWatermarkImage($logoPath, 0.15, [130, 120], 'F', true, 45, 90);
                    $mpdf->showWatermarkImage = true;
                }

// ✅ Write the HTML into the PDF
                $mpdf->WriteHTML($html);

                $filePath = $payslipDir . $payslip_no . ".pdf";
                $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);


                $message = 'Payroll record added successfully.';
                return $response
                    ->withHeader('Location', '/payroll?success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to add payroll record.';
                return $response
                    ->withHeader('Location', '/payroll?error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    //Update record
    public function updatePayroll(Request $request, Response $response, $args){
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $employee_id = trim($data['employee_id'] ?? '');
            $account_number = trim($data['account_number'] ?? '');
            $bank_name = trim($data['bank_name'] ?? '');
            $branch_name = trim($data['branch_name'] ?? '');
            $payroll_id = trim($data['payroll_id'] ?? '');

            // Validation rules
            $validator = [
                'employee_id' => v::notEmpty()->digit(),
                'account_number' => v::digit()->notEmpty(),
                'bank_name' => v::stringType()->notEmpty(),
                'branch_name' => v::stringType()->notEmpty(),
                'payroll_id' => v::notEmpty()->digit()->notEmpty()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }


            // If validation fails → return JSON error
            if (!empty($errors)) {
                //$payload = ['errors' => $errors];
                // Redirect back with errors
                $query = http_build_query([
                    'errors' => implode(', ', $errors)
                ]);
                return $response
                    ->withHeader('Location', '/payroll?' . $query)
                    ->withStatus(302);
            }

            $net_pay = salary::where('employee_id', $employee_id)->value('net_pay');

            // Fetch salary details
            $basic_pay = salary::where('employee_id', $employee_id)->value('basic_salary') ?? 0;
            // Fetch allowances and deductions
            $allowances = allowance::where('employee_id', $employee_id)->get();
            $deductions = deductions::where('employee_id', $employee_id)->get();

            $total_allowances = $allowances->sum('amount');
            $total_deductions = $deductions->sum('amount');
            // Save record
            $update = payroll::where('id', $payroll_id)->update([
                'employee_id' => $employee_id,
                'account_number' => $account_number,
                'bank_name' => $bank_name,
                'branch_name' => $branch_name,
                'net_pay' => $net_pay
            ]);

            if ($update) {
                // ----------------------------
                // Generate payslip with mPDF
                // ----------------------------

                $payslip_no = "FHT-" . str_pad($update->employee_id, 5, '0', STR_PAD_LEFT);

                $employee = employees::find($employee_id);
                $basic_pay = salary::where('employee_id', $employee_id)->value('basic_salary');
                $allowances = allowance::where('employee_id', $employee_id)->get();
                $deductions = deductions::where('employee_id', $employee_id)->get();

                $currentMonth = date('F'); // Returns full month name, e.g., "August"

                $gross_pay = $basic_pay;
                foreach ($allowances as $a) $gross_pay += $a->amount;

                $total_deductions = 0;
                foreach ($deductions as $d) $total_deductions += $d->amount;

                $net_pay = $gross_pay - $total_deductions;

// Build allowances HTML
                $allowances_html = "";
                foreach ($allowances as $a) {
                    $allowances_html .= "<tr><td>{$a->type}</td><td align='left'>" . number_format($a->amount, 2) . "</td><td>-</td></tr>";
                }

// Build deductions HTML
                $deductions_html = "";
                foreach ($deductions as $d) {
                    $deductions_html .= "<tr><td>{$d->type}</td><td>-</td><td align='left'>" . number_format($d->amount, 2) . "</td></tr>";
                }


                $html = "
<div style='text-align:center; margin-top: 20rem; font-family: \"Olswald\", sans-serif;'>
    <img src='/public/assets/img/fht.png' height='120' alt='Company Logo'>
    <h5>
        Synod Complex, Mosi-oa-Tunya Road, <br>
        Woodlands, Lusaka
    </h5>
    <h2>E-Payslip</h2>
    <h3 align='left'>Name:{$employee->name}</h3>
</div>

<!-- Header Info in single row -->
<div style='font-size:10px; margin-bottom:3em; font-family: \"Olswald\", sans-serif;'>
    <table width='100%' style='border-collapse:collapse;'>
        <tr>
            <td><strong>Date:</strong> " . date('d M Y') . "</td>
            <td><strong>Bank:</strong> {$bank_name}</td>
            <td><strong>Payslip #:</strong> {$payslip_no}</td>
            <td><strong>Month:</strong> {$currentMonth}</td>
        </tr>
    </table>
</div>

<table width='100%' border='1' cellspacing='0' cellpadding='3' style='border-collapse:collapse; font-size:10px; font-family: \"Olswald\", sans-serif;'>
    <tr>
        <th align='left'>Item / Description</th>
        <th align='left'>Allowances</th>
        <th align='left'>Deductions</th>
    </tr>
    <tr>
        <td>Basic Pay</td>
        <td align='left'>" . number_format($basic_pay, 2) . "</td>
        <td>-</td>
    </tr>
    {$allowances_html}
    {$deductions_html}
    <tr>
        <td><strong>Gross Pay</strong></td>
        <td colspan='2' align='left'><strong>" . number_format($gross_pay, 2) . "</strong></td>
    </tr>
    <tr>
        <td><strong>Net Pay</strong></td>
        <td colspan='2' align='left'><strong>" . number_format($net_pay, 2) . "</strong></td>
    </tr>
</table>
<p><strong>Total Net Payable:</strong>ZMW" . number_format($net_pay, 2) . " </p>
<!-- Footer horizontal line -->
<hr style='border:3px solid #000; margin-top:2em;'>
<div style='text-align:center; font-family: \"Olswald\", sans-serif; font-size:10px; margin-top: -3em'>
    <p>This is a system-generated payslip. Kindly report any under/over payments</p>
</div>
";


// Create mPDF instance
                $baseDir = dirname(__DIR__, 2);

// Define temp and payslip dirs
                $tempDir = $baseDir . '/public/assets/uploads/tmp';
                $payslipDir = $baseDir . '/public/assets/uploads/payslips/';


// Absolute logo path for watermark
                $logoPath = $baseDir . '/public/assets/img/fht.png';
// Ensure directories exist
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0777, true);
                }
                if (!is_dir($payslipDir)) {
                    mkdir($payslipDir, 0777, true);
                }

// Create mPDF instance with writable tempDir
                $mpdf = new Mpdf(['tempDir' => $tempDir]);

// ✅ Add watermark logo
                if (file_exists($logoPath)) {
                    $mpdf->SetWatermarkImage($logoPath, 0.15, [130, 120], 'F', true, 45, 90);
                    $mpdf->showWatermarkImage = true;
                }

// ✅ Write the HTML into the PDF
                $mpdf->WriteHTML($html);

                $filePath = $payslipDir . $payslip_no . ".pdf";
                $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

                $message = 'Payroll record updated successfully.';
                return $response
                    ->withHeader('Location', '/payroll?success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to update payroll record.';
                return $response
                    ->withHeader('Location', '/payroll?error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    //Freeze Payroll Record
    public function freezePayrollRecord(Request $request, Response $response, $args)
    {
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $payroll_id = trim($data['payroll_id'] ?? '');

            // Validation rules
            $validator = [
                'payroll_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                // Redirect back with errors
                $query = http_build_query([
                    'errors' => implode(', ', $errors)
                ]);
                return $response
                    ->withHeader('Location', '/payroll?' . $query)
                    ->withStatus(302);
            }

            // Save record
            // Perform delete
            $freeze = payroll::where('id', $payroll_id)->update([
                'status' => 'frozen'
            ]);

            if ($freeze) {
                $message = 'Payroll record frozen successfully.';
                return $response
                    ->withHeader('Location', '/payroll?success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to freeze payroll record.';
                return $response
                    ->withHeader('Location', '/payroll?error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    //Freeze Payroll Record
    public function unfreezePayrollRecord(Request $request, Response $response, $args)
    {
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $payroll_id = trim($data['payroll_id'] ?? '');

            // Validation rules
            $validator = [
                'payroll_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                // Redirect back with errors
                $query = http_build_query([
                    'errors' => implode(', ', $errors)
                ]);
                return $response
                    ->withHeader('Location', '/payroll?' . $query)
                    ->withStatus(302);
            }

            // Save record
            // Perform delete
            $freeze = payroll::where('id', $payroll_id)->update([
                'status' => 'active'
            ]);

            if ($freeze) {
                $message = 'Payroll record unfrozen successfully.';
                return $response
                    ->withHeader('Location', '/payroll?success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to unfreeze payroll record.';
                return $response
                    ->withHeader('Location', '/payroll?error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    //Delete Payroll Record
    public function deletePayrollRecord(Request $request, Response $response, $args)
    {
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $payroll_id = trim($data['payroll_id'] ?? '');

            // Validation rules
            $validator = [
                'payroll_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                // Redirect back with errors
                $query = http_build_query([
                    'errors' => implode(', ', $errors)
                ]);
                return $response
                    ->withHeader('Location', '/payroll?' . $query)
                    ->withStatus(302);
            }

            // Save record
            // Perform delete
            $freeze = payroll::where('id', $payroll_id)->delete();

            if ($freeze) {
                $message = 'Payroll record deleted successfully.';
                return $response
                    ->withHeader('Location', '/payroll?success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to deleted payroll record.';
                return $response
                    ->withHeader('Location', '/payroll?error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    public function history(Request $request, Response $response, $args){

        $history = payroll::all();
        return $this->view->render($response, 'transaction-history.twig', [
            'title' => 'Payroll History',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'payrollHistory' => $history,
        ]);
    }

    public function payslip(Request $request, Response $response, $args){

        $history = payroll::all();
        return $this->view->render($response, 'payslip.twig', [
            'title' => 'Payslip',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'payrollHistory' => $history,
        ]);
    }

    //Employees
    public function salary(Request $request, Response $response, $args){

        session_start();

        function getFlashMessages(array $queryParams): array
        {
            $types = ['allowance', 'deduction', 'salary'];
            $messages = [];

            foreach ($types as $type) {
                // Errors
                $errorKey = $type . 'Errors';
                $messages[$errorKey] = [];

                if (!empty($queryParams[$errorKey])) {
                    $decoded = json_decode($queryParams[$errorKey], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $messages[$errorKey] = $decoded;
                    }
                }

                // Success
                $successKey = $type . 'Success';
                $messages[$successKey] = $queryParams[$successKey] ?? null;
            }

            // Generic success message
            $messages['success'] = $queryParams['success'] ?? null;

            return $messages;
        }


//        $allowanceErrors = getErrorsFromQuery('allowanceErrors');
//        $deductionErrors = getErrorsFromQuery('deductionErrors');
//        $salaryErrors    = getErrorsFromQuery('salaryErrors');

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
        //$salaryData = payroll::all();
        // Fetch all employees
        $employeeData = employees::all();
        $allowance = allowance::all();
        $deductions = deductions::all();
        $salary = salary::all();
        //var_dump($allowance);

        $queryParams = $request->getQueryParams();
        $flashMessages = getFlashMessages($queryParams);


        return $this->view->render($response, 'salary-manager.twig', [
            'title' => 'Salary Management',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'salaries' => $salary,
            'employees' => $employeeData,
            'allowances' => $allowance,
            'deductions' => $deductions,
            //'success' => $success,
            'allowanceErrors' => $flashMessages['allowanceErrors'],
            'allowanceSuccess' => $flashMessages['allowanceSuccess'],
            'deductionErrors' => $flashMessages['deductionErrors'],
            'deductionSuccess' => $flashMessages['deductionSuccess'],
            'salaryErrors' => $flashMessages['salaryErrors'],
            'salarySuccess' => $flashMessages['salarySuccess'],
            'success' => $flashMessages['success'],
        ]);
    }


// Save Allowances
    public function allowance(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $type = trim($data['type'] ?? '');
            $amount = trim($data['amount'] ?? '');
            $employee_id = trim($data['employee_id'] ?? '');

            // Validation rules
            $validator = [
                'type' => v::notEmpty()->stringType(),
                'amount' => v::notEmpty(),
                'employee_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                $payload = ['errors' => $errors];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Save record
            $create = allowance::create([
                'employee_id' => $employee_id,
                'type' => $type,
                'amount' => $amount
            ]);

            if ($create) {
                $message = 'Allowance record added successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=allowance-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to add allowance record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=allowance-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    public function updateAllowance(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $type = trim($data['type'] ?? '');
            $amount = trim($data['amount'] ?? '');
            $employee_id = trim($data['employee_id'] ?? '');
            $allowance_id = trim($data['allowance_id'] ?? '');

            // Validation rules
            $validator = [
                'type' => v::notEmpty()->stringType(),
                'amount' => v::notEmpty(),
                'employee_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                $payload = ['errors' => $errors];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Save record
            $update = allowance::where('id', $allowance_id)->update([
                'employee_id' => $employee_id,
                'type' => $type,
                'amount' => $amount
            ]);

            if ($update) {
                $message = 'Allowance record updated successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=allowance-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to update allowance record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=allowance-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    //Get All allowance data
    public function allowanceList(Request $request, Response $response, $args)
    {
        //Get all allowance data from the database
        session_start();
        $allowance = allowance::orderBy('created_at', 'desc')->get();

        $response->getBody()->write(json_encode($allowance));
        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    public function deleteAllowanceRecord(Request $request, Response $response, $args){
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $allowance_id = trim($data['allowance_id'] ?? '');

            // Validation rules
            $validator = [
                'allowance_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                $payload = ['errors' => $errors];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Save record
            // Perform delete
            $delete = allowance::where('id', $allowance_id)->delete();

            if ($delete) {
                $message = 'Allowance record deleted successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=allowance-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to delete allowance record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=allowance-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    // Save Deductions
    public function deductions(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $type = trim($data['type'] ?? '');
            $amount = trim($data['amount'] ?? '');
            $employee_id = trim($data['employee_id'] ?? '');

            // Validation rules
            $validator = [
                'type' => v::notEmpty()->stringType(),
                'amount' => v::notEmpty(),
                'employee_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                $payload = ['errors' => $errors];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Save record
            $create = deductions::create([
                'employee_id' => $employee_id,
                'type' => $type,
                'amount' => $amount
            ]);

            if ($create) {
                $message = 'Deduction record added successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=deduction-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to add deduction record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=deduction-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    // Save Deductions
    public function updateDeductions(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $type = trim($data['type'] ?? '');
            $amount = trim($data['amount'] ?? '');
            $employee_id = trim($data['employee_id'] ?? '');
            $deduction_id = trim($data['deduction_id']);

            // Validation rules
            $validator = [
                'type' => v::notEmpty()->stringType(),
                'amount' => v::notEmpty(),
                'employee_id' => v::notEmpty()->digit(),
                'deduction_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                $payload = ['errors' => $errors];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Save record
            $update = deductions::where('id',$deduction_id)->update([
                'employee_id' => $employee_id,
                'type' => $type,
                'amount' => $amount
            ]);

            if ($update) {
                $message = 'Deduction record updated successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=deduction-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to update deduction record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=deduction-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    //Delete deduction records
    public function deleteDeductionRecord(Request $request, Response $response, $args){
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $deduction_id = trim($data['deduction_id'] ?? '');

            // Validation rules
            $validator = [
                'deduction_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                $payload = ['errors' => $errors];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Save record
            // Perform delete
            $delete = deductions::where('id', $deduction_id)->delete();

            if ($delete) {
                $message = 'Deduction record deleted successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=deduction-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to delete Deduction record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=deduction-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }

    //Show all deductions
    public function deductionList(Request $request, Response $response, $args){
        session_start();
        $allowance = allowance::orderBy('created_at', 'desc')->get();

        $response->getBody()->write(json_encode($allowance));
        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    //Get totals
    public function totals(Request $request, Response $response, $args)
    {
        $employeeId = (int)$args['employee_id'];

        // Sum allowances
        $totalAllowance = allowance::where('employee_id', $employeeId)->sum('amount');

        // Sum deductions
        $totalDeductions = deductions::where('employee_id', $employeeId)->sum('amount');

        $data = [
            'total_allowance' => $totalAllowance,
            'total_deductions' => $totalDeductions
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    //Add Salary
    public function addSalary(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $employee_id   = trim($data['employee_id'] ?? '');
            $basic_pay     = (float)($data['basic_pay'] ?? 0);
            $pay_frequency = trim($data['pay_frequency'] ?? '');

            $errors = [];

            // Basic validation
            if (empty($employee_id) || !ctype_digit($employee_id)) {
                $errors[] = 'Employee ID is required or invalid.';
            }
            if (empty($basic_pay) || !is_numeric($basic_pay)) {
                $errors[] = 'Basic pay is required or invalid.';
            }
            if (empty($pay_frequency)) {
                $errors[] = 'Pay frequency is required.';
            }

            // Check if salary exists
            if ($employee_id && salary::where('employee_id', $employee_id)->exists()) {
                $errors[] = 'This employee already has a salary record.';
            }

            if (!empty($errors)) {
                // Redirect back with errors
                $query = http_build_query([
                    'tab' => 'salary-tab',
                    'error' => implode(', ', $errors)
                ]);
                return $response
                    ->withHeader('Location', '/payroll/salary?' . $query)
                    ->withStatus(302);
            }

            // Calculate totals
            $totalAllowance = allowance::where('employee_id', $employee_id)->sum('amount');
            $totalDeduction = deductions::where('employee_id', $employee_id)->sum('amount');
            $gross_pay = $basic_pay + $totalAllowance;
            $net_pay = $gross_pay - $totalDeduction;

            // Save salary
            $create = salary::create([
                'employee_id'     => $employee_id,
                'basic_salary'    => $basic_pay,
                'total_allowance' => $totalAllowance,
                'total_deductions' => $totalDeduction,
                'gross_pay'       => $gross_pay,
                'net_pay'         => $net_pay,
                'pay_frequency'   => $pay_frequency,
            ]);

            if ($create) {
                $message = 'Salary record added successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=salary-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to add salary record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=salary-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }

        // GET request: render the page
        $employeeData = employees::all();
        $allowanceData = allowance::all();
        $deductionData = deductions::all();
        $salaryData = salary::all();

        return $this->view->render($response, 'salary-manager.twig', [
            'title' => 'Salary Management',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'salaries' => $salaryData,
            'employees' => $employeeData,
            'allowances' => $allowanceData,
            'deductions' => $deductionData,
        ]);
    }

    //Add Salary
    public function updateSalary(Request $request, Response $response, $args)
    {
        session_start();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            $employee_id   = trim($data['employee_id'] ?? '');
            $basic_pay     = (float)($data['basic_pay'] ?? 0);
            $pay_frequency = trim($data['pay_frequency'] ?? '');
            $salary_id = trim($data['salary_id']);

            $errors = [];

            // Basic validation
            if (empty($employee_id) || !ctype_digit($employee_id)) {
                $errors[] = 'Employee ID is required or invalid.';
            }
            if (empty($basic_pay) || !is_numeric($basic_pay)) {
                $errors[] = 'Basic pay is required or invalid.';
            }
            if (empty($pay_frequency)) {
                $errors[] = 'Pay frequency is required.';
            }

            if (!empty($errors)) {
                // Redirect back with errors
                $query = http_build_query([
                    'tab' => 'salary-tab',
                    'error' => implode(', ', $errors)
                ]);
                return $response
                    ->withHeader('Location', '/payroll/salary?' . $query)
                    ->withStatus(302);
            }

            // Calculate totals
            $totalAllowance = allowance::where('employee_id', $employee_id)->sum('amount');
            $totalDeduction = deductions::where('employee_id', $employee_id)->sum('amount');
            $gross_pay = $basic_pay + $totalAllowance;
            $net_pay = $gross_pay - $totalDeduction;

            // Save salary
            $create = salary::where('id', $salary_id)->update([
                'employee_id'     => $employee_id,
                'basic_salary'    => $basic_pay,
                'total_allowance' => $totalAllowance,
                'total_deductions' => $totalDeduction,
                'gross_pay'       => $gross_pay,
                'net_pay'         => $net_pay,
                'pay_frequency'   => $pay_frequency,
            ]);

            if ($create) {
                $message = 'Salary record updated successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=salary-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to update salary record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=salary-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }

        // GET request: render the page
        $employeeData = employees::all();
        $allowanceData = allowance::all();
        $deductionData = deductions::all();
        $salaryData = salary::all();

        return $this->view->render($response, 'salary-manager.twig', [
            'title' => 'Salary Management',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
            'salaries' => $salaryData,
            'employees' => $employeeData,
            'allowances' => $allowanceData,
            'deductions' => $deductionData,
        ]);
    }

    //Delete salary record
    public function deleteSalaryRecord(Request $request, Response $response, $args){
        session_start();
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $salary_id = trim($data['salary_id'] ?? '');

            // Validation rules
            $validator = [
                'salary_id' => v::notEmpty()->digit()
            ];

            // Validate
            $errors = [];
            foreach ($validator as $field => $rule) {
                if (!$rule->validate($$field)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required or invalid.';
                }
            }

            // If validation fails → return JSON error
            if (!empty($errors)) {
                $payload = ['errors' => $errors];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Save record
            // Perform delete
            $delete = salary::where('id', $salary_id)->delete();

            if ($delete) {
                $message = 'Salary record deleted successfully.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=salary-tab&success=' . urlencode($message))
                    ->withStatus(302);
            } else {
                $message = 'Failed to delete Salary record.';
                return $response
                    ->withHeader('Location', '/payroll/salary?tab=salary-tab&error=' . urlencode($message))
                    ->withStatus(302);
            }
        }
    }






}