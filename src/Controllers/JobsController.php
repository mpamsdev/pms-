<?php

namespace App\Controllers;

use App\Models\Jobs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class JobsController
{
    // Create a job
    public function create(Request $request, Response $response): Response
    {
            $data = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        // Handle file upload (company_logo)
        $logoPath = null;
        if (!empty($uploadedFiles['company_logo'])) {
            $logo = $uploadedFiles['company_logo'];
            if ($logo->getError() === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public/assets/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filename = uniqid() . '_' . preg_replace('/\s+/', '_', $logo->getClientFilename());
                $logo->moveTo($uploadDir . $filename);
                //$logoPath = 'assets/img/' . $filename;
            }
        }

        $position = strtolower(trim($data['position'] ?? ''));

        // Check if it's a single word (no spaces)
        if (preg_match('/^\w+$/', $position)) {
            $slug = strtolower($position);
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $data['position'] ?? ''), '-'));
        }


        // Insert job
        Jobs::create([
            'company_name' => $data['company_name'] ?? null,
            'company_logo' => $filename,
            'email'        => $data['email'] ?? null,
            'position'     => $data['position'] ?? null,
            'salary_range' => $data['salary'] ?? null,
            'description'  => $data['description'] ?? null,
            'slug'         => $slug,
            'job_type'     => $data['job_type'] ?? null,
            'location'     => $data['location'] ?? null,
            'website'      => $data['website'] ?? null,
            'deadline'     => $data['deadline'] ?? null,
        ]);

        return $response
            ->withHeader('Location', '/admins/dashboard') // Redirect to dashboard after creation
            ->withStatus(302);
    }

    // Get all jobs (paginated)
//    public function getAllJobs(Request $request, Response $response): Response
//    {
//        $params = $request->getQueryParams();
//        $page = isset($params['page']) ? (int)$params['page'] : 1;
//        $limit = 3; // Default limit per page
//        $offset = ($page - 1) * $limit;
//
//        $total = Jobs::count();
//        $jobs = Jobs::offset($offset)->limit($limit)->get();
//
//        $result = [
//            'data' => $jobs,
//            'total' => $total,
//            'per_page' => $limit,
//            'current_page' => $page,
//            'last_page' => ceil($total / $limit),
//        ];
//
//        $response->getBody()->write(json_encode($result));
//        return $response->withHeader('Content-Type', 'application/json');
//    }

    public function getAllJobs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = 3;
        $offset = ($page - 1) * $limit;

        $total = Jobs::count();
        $jobs = Jobs::offset($offset)->limit($limit)->get();

        return $this->view->render($response, 'home.twig', [
            'jobs' => $jobs,
            'pagination' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => ceil($total / $limit),
            ],
        ]);
    }

    //Load More Jobs
    public function loadMoreJobs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = 3;
        $offset = ($page - 1) * $limit;

        $morejobs = Jobs::offset($offset)->limit($limit)->get();

        return $this->view->render($response, 'partials/job_rows.twig', [
            'morejobs' => $morejobs
        ]);
    }

    // Get job by ID
    public function getById(Request $request, Response $response, array $args): Response
    {
        $job = Jobs::find($args['id']);

        if (!$job) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Job not found']));
        }

        $response->getBody()->write(json_encode($job));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Get job by slug
    public function getBySlug(Request $request, Response $response, array $args): Response
    {
        $job = Jobs::where('slug', $args['slug'])->first();

        if (!$job) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Job not found']));
        }

        $response->getBody()->write(json_encode($job));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Update a job
    public function update(Request $request, Response $response, array $args): Response
    {
        $job = Jobs::find($args['id']);

        if (!$job) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Job not found']));
        }

        $data = $request->getParsedBody();
        $job->update($data);

        $response->getBody()->write(json_encode($job));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Delete a job
    public function delete(Request $request, Response $response, array $args): Response
    {
        $job = Jobs::find($args['id']);

        if (!$job) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Job not found']));
        }

        $job->delete();

        $response->getBody()->write(json_encode(['message' => 'Job deleted']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
