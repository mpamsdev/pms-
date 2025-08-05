<?php

namespace App\Controllers;

use App\Models\loan_products;
use App\Models\loans;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Carbon\Carbon;
use App\Models\employees;
use Illuminate\Pagination\Paginator;
use Ramsey\Uuid\Uuid;
use Dompdf\Dompdf;
use Dompdf\Options;


class HomeController
{
    protected $view;
    //protected $db;

    public function __construct(Twig $view)
    {
        $this->view = $view;
        //$this->db = $db;
    }

    /*
     * Dashboard  View
     */
    public function dashboard(Request $request, Response $response, $args){
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

        // Render the dashboard
        return $this->view->render($response, 'dashboard.twig', [
            'title' => 'Dashboard',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profile_picture' => $_SESSION['profile_picture'],
            'userData' => $_SESSION['userData'],
        ]);
    }


    /*
     * Dashboard  View
     */
    public function profile(Request $request, Response $response, $args){
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

        $profile = employees::where('userid', $_SESSION['userid'])->first();

        // Render the dashboard
        return $this->view->render($response, 'profile.twig', [
            'title' => 'My Profile',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profileData' => $profile,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //Generate UUID
    public function generateUUIDs(Request $request, Response $response, $args): Response
    {
        $loans = \App\Models\admins::whereNull('uuid')->get();
        $total = count($loans);
        $log = [];

        if ($total === 0) {
            $log[] = "✅ All loans already have UUIDs.";
        } else {
            foreach ($loans as $loan) {
                $uuid = Uuid::uuid4()->toString();
                $loan->uuid = $uuid;
                $loan->save();
                //$log[] = "🔐 Assigned UUID {$uuid} to Loan ID {$loan->app_id}";
            }

            $log[] = "✅ Finished assigning UUIDs to {$total} loans.";
        }

        $response->getBody()->write(implode("<br>", $log));
        return $response;
    }

    //All loans view
    public function allLoans(Request $request, Response $response, $args){
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

        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });

        $loans = loans::with('user')->orderBy('app_id', 'desc')
            ->paginate(20);

        //var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'compliance-table.twig', [
            'title' => 'All Loans',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loans' => $loans,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
    public function deletedLoans(Request $request, Response $response, $args){
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


        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });


        $loans = loans::with('user')->onlyTrashed()->orderBy('deleted_at', 'desc')
            ->paginate(20);          // paginate results

        //$loans = loans::onlyTrashed()->get();


        //var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'deleted-loans.twig', [
            'title' => 'Deleted Loans',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loans' => $loans,
            'userData' => $_SESSION['userData'],
        ]);
    }


    //All loans view
    public function cancelledLoans(Request $request, Response $response, $args){
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


        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });


        $loans = loans::with('user')
            ->where('status', 4)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Render the dashboard
        return $this->view->render($response, 'cancelled-loans.twig', [
            'title' => 'Cancelled Loans',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loans' => $loans,
            'userData' => $_SESSION['userData'],
        ]);
    }


    //All loans view
    public function rejectedLoans(Request $request, Response $response, $args){
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


        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });


        $loans = loans::with('user')
            ->where('status', 3)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        ///var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'rejected-loans.twig', [
            'title' => 'Rejected Loans',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loans' => $loans,
            'userData' => $_SESSION['userData'],
        ]);
    }


    //All loans view
    public function paidLoans(Request $request, Response $response, $args){
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


        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });


        $loans = loans::with('user')
            ->where('status', 6)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        ///var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'paid-loans.twig', [
            'title' => 'Paid Loans',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loans' => $loans,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
    public function repaymentLoans(Request $request, Response $response, $args){
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


        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });


        $loans = loans::with('user')
            ->where('status', 5)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        ///var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'repayment-loans.twig', [
            'title' => 'Loan Repayment',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loans' => $loans,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
    public function myApplications(Request $request, Response $response, $args){
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


        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });

        $userid = $_SESSION['userid'];

        $loans = loans::with('user') // Eager load the user
        ->where('userid', $userid)
            ->orderBy('created_at', 'desc')
            ->paginate(20);


        //var_dump($_SESSION['userData']);

        // Render the dashboard
        return $this->view->render($response, 'my-applications.twig', [
            'title' => 'My Applications',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loans' => $loans,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
    public function viewLoan(Request $request, Response $response, $args){
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

        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });

        //$loan = loans::with('user')->where('uuid', $args['uuid'])->firstOrFail();
        $loan = loans::with([
            'user.occupation',
            'user.nextOfKin'
        ])->where('uuid', $args['uuid'])->firstOrFail();


        ///var_dump($loan);

        // Render the dashboard
        return $this->view->render($response, 'loan-details.twig', [
            'title' => 'Loan Details',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'loan' => $loan,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //view client view
    public function viewClient(Request $request, Response $response, $args){
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

        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });

        $client = \App\Models\employees::with(['occupation', 'nextOfKin'])
            ->where('uuid', $args['uuid'])
            ->firstOrFail();

        // Render the dashboard
        return $this->view->render($response, 'view-client.twig', [
            'title' => 'Client Details',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'client' => $client,
            'userData' => $_SESSION['userData'],
        ]);
    }

    /*
     * Dashboard  View
     */
    public function search(Request $request, Response $response, $args){
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

        $profile = employees::where('userid', $_SESSION['userid'])->first();

        // Render the dashboard
        return $this->view->render($response, 'deep-search.twig', [
            'title' => 'Deep Search',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'profileData' => $profile,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
    public function clients(Request $request, Response $response, $args){
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

        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });

        $users = employees::orderBy('id', 'desc')->paginate(20);

        //var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'clients.twig', [
            'title' => 'All Users',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'users' => $users,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
    public function companies(Request $request, Response $response, $args){
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

        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });

        $users = \App\Models\companies::orderBy('id', 'desc')->paginate(20);

        //var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'companies.twig', [
            'title' => 'All Users',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            'companies' => $users,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
    public function receipts(Request $request, Response $response, $args){
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

        Paginator::currentPageResolver(function ($pageName = 'page') {
            return $_GET[$pageName] ?? 1;
        });

        $users = \App\Models\companies::orderBy('id', 'desc')->paginate(20);

        //var_dump($loans);

        // Render the dashboard
        return $this->view->render($response, 'receipts.twig', [
            'title' => 'Receipts',
            'username' => $_SESSION['username'],
            'userid' => $_SESSION['userid'],
            //'companies' => $users,
            'userData' => $_SESSION['userData'],
        ]);
    }

    //All loans view
//    public function downloadPdf(Request $request, Response $response, $args){
//        session_start();
//
//
//        // Check if user is logged in
//        if (!isset($_SESSION['userid']) || !isset($_SESSION['username'])) {
//            return $response
//                ->withHeader('Location', '/')
//                ->withStatus(302);
//        }
//
//        // Check if session has expired
//        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $_SESSION['session_timeout']) {
//            // If the session has expired, destroy it and redirect to the login page
//            session_unset();
//            session_destroy();
//            return $response
//                ->withHeader('Location', '/')
//                ->withStatus(302);
//        }
//
//
//        $uuid = $args['uuid'];
//
//        $loan = loans::with('user')->where('uuid', $uuid)->firstOrFail();
//
//        // Render HTML view
//        $html = $this->get('view')->fetch('pdf-loan-details.twig', [
//            'loan' => $loan
//        ]);
//
//        // Setup Dompdf
//        $options = new Options();
//        $options->set('defaultFont', 'DejaVu Sans');
//        $dompdf = new Dompdf($options);
//        $dompdf->loadHtml($html);
//        $dompdf->setPaper('A4', 'portrait');
//        $dompdf->render();
//
//        // Return PDF response
//        $output = $dompdf->output();
//        $res = new Response();
//        $res->getBody()->write($output);
//
//        return $res
//            ->withHeader('Content-Type', 'application/pdf')
//            ->withHeader('Content-Disposition', 'attachment; filename="loan-details.pdf"');
//
//        //var_dump($loan);
//
//        // Render the dashboard
//        return $this->view->render($response, 'loan-details.twig', [
//            'title' => 'Loan Details',
//            'username' => $_SESSION['username'],
//            'userid' => $_SESSION['userid'],
//            'loan' => $loan,
//            'userData' => $_SESSION['userData'],
//        ]);
//    }




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


    public function error404(Request $request, Response $response, $args)
    {
        // Start the session
        session_start();

        // Determine if the user is logged in
        $isLoggedIn = isset($_SESSION['userid']) && isset($_SESSION['username']);

        // Render the 404 page
        return $this->view->render($response->withStatus(404), '404.twig', [
            'title' => 'Page Not Found',
            'isLoggedIn' => $isLoggedIn, // Pass the session state to the template
        ]);
    }
}