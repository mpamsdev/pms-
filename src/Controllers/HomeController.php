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

        $profile = employees::where('id', $_SESSION['userid'])->first();
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