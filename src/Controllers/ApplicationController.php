<?php

namespace App\Controllers;

use App\Models\loan_products;
use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Carbon\Carbon;
use App\Models\loans;

class ApplicationController
{
    protected $view;
    //protected $db;

    public function __construct(Twig $view)
    {
        $this->view = $view;
        //$this->db = $db;
    }

    /*
     * Application  View
     */
    public function apply(Request $request, Response $response, $args){
        // Start the session
        session_start();

        // Check if user is logged in
        if (!isset($_SESSION['userid']) || !isset($_SESSION['username'])) {
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        // Check if session has expired
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $_SESSION['session_timeout']) {
            // If the session has expired, destroy it and redirect to the login page
            session_unset();
            session_destroy();
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        $products = loan_products::all();

        // Render the dashboard
        return $this->view->render($response, 'apply.twig', [
            'title' => 'Apply',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'products' => $products,
            'userData' => $_SESSION['userData'],
        ]);
    }

    public function loanAction(Request $request, Response $response, $args): Response
    {
        $data = $request->getParsedBody();

        $loanUuid = $data['loan_uuid'] ?? null;
        $actionType = $data['action_type'] ?? null;
        $comment = trim($data['comment'] ?? '');

        if (!$loanUuid || !$actionType || !$comment) {
            // Handle invalid input (redirect back with error flash maybe)
            return $response->withStatus(400)->write('Invalid form submission');
        }

        // Find loan by UUID
        $loan = loans::where('uuid', $loanUuid)->first();
        if (!$loan) {
            return $response->withStatus(404)->write('Loan not found');
        }

        // Change status based on action
        switch ($actionType) {
            case 'verify':
                $loan->status = '0';  // verified
                break;
            case 'cancel':
                $loan->status = '4';  // cancelled
                break;
            case 'approve':
                $loan->status = '1';  // approved
                break;
            case 'reject':
                $loan->status = '3';  // rejected
                break;
            case 'disburse':
                $loan->status = '2';  // disbursed
                break;
            default:
                return $response->withStatus(400)->write('Invalid action');
        }

        // Save comment to a log table or related entity - implement your own logging here
        $loan->comment = $comment;
        $loan->save();

        // You can also call your logging function here, e.g.:
        // $this->logAction($userId, $username, "Performed {$actionType} on loan {$loan->applicationNumber}");

        // Redirect back or return JSON success
        return $response->withHeader('Location', '/loan/loan-details/' . $loanUuid)->withStatus(302);
    }

}