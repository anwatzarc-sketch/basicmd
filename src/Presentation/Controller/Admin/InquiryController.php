<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Controller\Admin;

use MediCareMini\Domain\Enum\InquiryStatus;
use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Infrastructure\Persistence\AuditLogger;
use MediCareMini\Infrastructure\Persistence\InquiryRepository;
use MediCareMini\Infrastructure\Security\SessionManager;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Presentation\Controller\Controller;
use MediCareMini\Presentation\Http\Request;
use MediCareMini\Presentation\Http\Response;
use MediCareMini\Presentation\View\View;

/**
 * Patient enquiry inbox.
 */
final class InquiryController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly InquiryRepository $inquiries,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->input('status'),
            'search' => $request->input('q'),
        ];

        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginate($request, 25);

        $total = $this->inquiries->countSearch($filters);

        return $this->renderAdmin('admin/inquiries/index', [
            'inquiries'  => $this->inquiries->search($filters, $perPage, $offset),
            'statuses'   => InquiryStatus::all(),
            'filters'    => $filters,
            'unread'     => $this->inquiries->countUnread(),
            'pagination' => $this->paginationMeta($total, $page, $perPage),
            'meta'       => ['title' => 'Patient Enquiries', 'noindex' => true],
        ]);
    }

    public function show(Request $request): Response
    {
        $inquiry = $this->inquiries->findById($request->routeInt('id'));

        if ($inquiry === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/inquiries/show', [
            'inquiry'  => $inquiry,
            'statuses' => InquiryStatus::all(),
            'history'  => $this->audit->forTarget('inquiry', $inquiry->id),
            'meta'     => ['title' => 'Enquiry from ' . $inquiry->name, 'noindex' => true],
        ]);
    }

    public function update(Request $request): Response
    {
        $user    = $this->requireUser();
        $id      = $request->routeInt('id');
        $inquiry = $this->inquiries->findById($id);

        if ($inquiry === null) {
            throw HttpException::notFound();
        }

        $status = InquiryStatus::tryFrom($request->string('status')) ?? $inquiry->status;

        $this->inquiries->updateStatus($id, $status, $user->id, $request->input('notes'));

        $this->audit->record(
            'inquiry.updated',
            'inquiry',
            $id,
            sprintf('Enquiry marked %s', $status->label()),
        );

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/inquiries/' . $id,
            'Enquiry updated.',
        );
    }

    public function delete(Request $request): Response
    {
        $id = $request->routeInt('id');

        $this->inquiries->softDelete($id);
        $this->audit->record('inquiry.deleted', 'inquiry', $id, 'Deleted enquiry');

        return $this->redirectWithSuccess($this->config->adminPath . '/inquiries', 'Enquiry deleted.');
    }
}
