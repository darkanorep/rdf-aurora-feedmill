<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdditionalAttachmentRequest;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ResponseController extends Controller
{
    private $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }
    public function index(Request $request) {
        return $this->responseService->getResponses($request);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'checklist_id'   => ['nullable', 'integer', 'exists:checklists,id'],
            'unit_id'        => ['nullable', 'integer', 'exists:units,id'],
            'start_at'       => ['nullable', 'date'],
            'is_completed'   => ['nullable', 'boolean'],
            'batch_no'       => ['nullable', 'integer'],
            'good_points'    => ['nullable', 'string'],
            'remarks'        => ['nullable', 'string'],
            'notes'          => ['nullable', 'string'],
            'temporal_audit' => ['nullable'],            // tighten once the shape is known
            'response'       => ['nullable', 'array', 'min:1', 'max:200'],
            'response.*'     => ['nullable', 'json'],    // stricter than parseData(); see note below
            'image'          => ['sometimes', 'array'],
            'image.*'        => ['array', 'max:5'],      // max images per response
            'image.*.*'      => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // KB, tune this
            'requires_evaluator' => ['nullable', 'boolean'],
            'evaluator_name'      => ['nullable', 'string', 'max:150', 'required_with:evaluator_signature'],
            'evaluator_signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'required_with:evaluator_name'],
            'keep_image'     => ['sometimes', 'array'],
            'keep_image.*'   => ['array'],
            'keep_image.*.*' => ['integer'],
            'remove_evaluator' => ['nullable', 'boolean']
        ]);

        $response = $this->responseService->storeResponse($data);

        return response()->json([
            'message' => 'Response saved successfully.',
            'data' => $response,
        ], 201, [], JSON_UNESCAPED_SLASHES);
    }
    public function summaryReportByBatchNo(Request $request) {
        $batchNo = $request->input('batch_no');

        return $this->responseService->generateSummaryReportByBatchNo($batchNo);
    }
    public function evaluateResponse(Request $request) {
        $data = $request->all();

        $this->responseService->evaluate($data);

        return response()->json([
            'message' => 'Response evaluate successfully.',
        ], 200);
    }
    public function assessResponse(Request $request) {
        $data = $request->all();
        $this->responseService->assess($data);
        return response()->json([
            'message' => 'Response assess successfully.',
        ], 200);
    }
    public function mergeResponse(Request $request) {
        $data = $request->all();

        $this->responseService->mergeResponse($data);

        return response()->json([
            'message' => 'Response merge successfully.',
        ]);
    }
    public function storeAdditionalAttachment(StoreAdditionalAttachmentRequest $request)
    {
        $this->responseService->additionalAttachment(
            $request->file('image'),
            $request->input('response_id')
        );

        return response()->json([
            'message' => 'Attachments uploaded successfully.',
            'response_id' => $request->input('response_id'),
        ]);
    }

    public function returned(Request $request)
    {
        $section = strtolower(trim((string) $request->input('section')));
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        $page = Submission::query()
            ->join('checklists', 'checklists.id', '=', 'submissions.checklist_id')
            ->join('sections', 'sections.id', '=', 'checklists.section_id')
            ->leftJoin('units', 'units.id', '=', 'submissions.unit_id')
            ->where('submissions.user_id', auth()->id())
            ->whereNotNull('submissions.returned_at')
            ->when($section !== '', fn ($q) => $q->whereRaw('LOWER(sections.name) = ?', [$section]))
            ->orderByDesc('submissions.returned_at')
            ->orderByDesc('submissions.id')      // stable order, so a row never repeats across pages
            ->paginate(
                $perPage,
                [
                    'submissions.id',
                    'submissions.legacy_batch_no',
                    'submissions.checklist_id',
                    'submissions.unit_id',
                    'submissions.start_at',
                    'submissions.returned_at',
                    'checklists.checklist_name',
                    'sections.name as section',
                    'units.name as unit',
                ],
                'page',
                max(1, (int) $request->input('page', 1))
            );

        // The latest rejection of each checklist on THIS page only.
        $rejections = SubmissionEvent::whereIn('submission_id', $page->getCollection()->pluck('id'))
            ->where('type', 'rejected')
            ->orderBy('id')
            ->get()
            ->groupBy('submission_id')
            ->map(fn ($events) => $events->last());

        return $page->through(function ($s) use ($rejections) {
            $r = $rejections->get($s->id);

            return [
                'batch_no'       => (int) $s->legacy_batch_no,
                'section'        => strtolower((string) $s->section),
                'checklist_id'   => $s->checklist_id,
                'checklist_name' => $s->checklist_name,
                'unit_id'        => $s->unit_id,
                'unit'           => $s->unit,
                'start_at'       => $s->start_at,
                'status'         => 'Rejected',
                'rejection'      => $r ? [
                    'at'     => $r->created_at,
                    'by'     => $r->actor_name,
                    'step'   => $r->step_label,
                    'reason' => $r->comment,
                ] : null,
            ];
        });
    }

    public function returnedCount()
    {
        return $this->responseService->returnedCount();
    }

    public function truncateResponse() {
        $this->responseService->truncateResponse();

        return response()->json([
            'message' => 'Response truncate successfully.',
        ]);
    }
}
