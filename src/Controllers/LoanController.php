<?php
namespace App\Controllers;

use App\Models\loan_products;
use App\Models\loans;
use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Carbon\Carbon;
use App\Models\employee;
use Illuminate\Pagination\Paginator;
use Ramsey\Uuid\Uuid;

class LoanController extends Model{
    protected $view;
    //protected $db;

    public function __construct(Twig $view)
    {
        $this->view = $view;
        //$this->db = $db;
    }

    //Delete loan
    public function deleteLoan(Request $request, Response $response, $args): Response
    {
        $uuid = $args['uuid'];
        $loan = loans::where('uuid', $uuid)->first();

        if (!$loan) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Loan not found'
            ]));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $loan->delete(); // Soft delete

        // Optionally, add a log entry for this action

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Loan soft deleted'
        ]));
        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    //Restore a loan
    public function restoreLoan(Request $request, Response $response, $args): Response
    {
        $uuid = $args['uuid'];
        $loan = loans::withTrashed()->where('uuid', $uuid)->first();

        if ($loan) {
            $loan->restore();
            $response->getBody()->write(json_encode(['success' => true, 'message' => 'Loan restored']));
            return $response->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to restore loan. Try again']));
        return $response->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    //Restore allLoans
    public function restoreAllLoans(Request $request, Response $response, $args): Response
    {
        $restored = loans::onlyTrashed()->restore();

        if ($restored > 0) {
            $response->getBody()->write(json_encode(['success' => true, 'message' => "$restored loan(s) restored."]));
            return $response->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        }

        // Explicit fallback for zero restorations
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'No loans to restore.']));
        return $response->withStatus(200) // Not a 404, just no work was done
        ->withHeader('Content-Type', 'application/json');
    }

    //Deep Search
    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $term = isset($params['term']) ? trim($params['term']) : '';

        if (empty($term)) {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $results = loans::where('applicationNumber', 'LIKE', "%{$term}%")
            ->orWhere('userid', 'LIKE', "%{$term}%")
            ->orWhereHas('user', function($query) use ($term) {
                $query->where('firstname', 'LIKE', "%{$term}%")
                    ->orWhere('lastname', 'LIKE', "%{$term}%");
            })
            ->with('user')
            ->get();

        // Format results
        $formatted = $results->map(function ($loan) {
            return [
                'title' => $loan->user->title ?? '',
                'firstname' => $loan->user->firstname ?? '',
                'lastname' => $loan->user->lastname ?? '',
                'fullname' => ($loan->user->firstname ?? '') . ' ' . ($loan->user->lastname ?? ''),
                'userid' => $loan->userid,
                'applicationNumber' => $loan->applicationNumber,
                'amount' => $loan->amount,
                'interestRate' => $loan->interestRate,
                'totalPayable' => $loan->totalPayable,
                'balance' => $loan->balance,
                'paid' => $loan->paid,
                'period' => $loan->period,
                'status' => $loan->status,
                'app_id' => $loan->id,
                'created_at' => $loan->created_at->format('d F, Y'),
                'updated_at' => $loan->updated_at->format('d F, Y'),
                'uuid' => $loan->uuid,
            ];
        });

        $response->getBody()->write(json_encode($formatted));
        return $response->withHeader('Content-Type', 'application/json');
    }



}