<?php

namespace App\Http\Controllers\Cms;

use App\Enums\RoleName;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\SubmissionUpdateRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Submission::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = (int) $request->integer('per_page', 25);

        $query = Submission::query()->with('assignee');
        $this->applyView($query, $view, $request->user());
        $this->applyFilters($query, $request);
        $query->orderByDesc('created_at');

        $submissions = $query->paginate($perPage)->withQueryString();

        return Inertia::render('submissions/index', [
            'submissions' => SubmissionResource::collection($submissions->items())->resolve(),
            'meta' => [
                'from' => $submissions->firstItem(),
                'to' => $submissions->lastItem(),
                'total' => $submissions->total(),
                'per_page' => $submissions->perPage(),
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'prev' => $submissions->previousPageUrl(),
                'next' => $submissions->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => ['statuses' => SubmissionStatus::options()],
        ]);
    }

    public function show(Submission $submission): Response
    {
        $this->authorize('view', $submission);

        $submission->load(['assignee', 'region', 'comments.author']);

        return Inertia::render('submissions/show', [
            'submission' => [
                'id' => $submission->id,
                'tracking_number' => $submission->tracking_number,
                'name' => $submission->name,
                'email' => $submission->email,
                'phone' => $submission->phone,
                'topic' => $submission->topic,
                'message' => $submission->message,
                'status' => $submission->status->value,
                'assigned_to' => $submission->assigned_to,
                'region' => $submission->region?->getTranslation('name', 'ru'),
                'ip_address' => $submission->ip_address,
                'created_at' => $submission->created_at?->toIso8601String(),
                'comments' => $submission->comments->map(fn (SubmissionComment $c): array => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author' => $c->user_id !== null ? $c->author->name : 'Система',
                    'created_at' => $c->created_at?->toIso8601String(),
                    'created_diff' => $c->created_at?->diffForHumans() ?? '',
                ])->all(),
            ],
            'reference' => [
                'statuses' => SubmissionStatus::options(),
                'assignees' => $this->assignees(),
            ],
        ]);
    }

    public function update(SubmissionUpdateRequest $request, Submission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $submission->status = SubmissionStatus::from($request->string('status')->toString());
        $submission->assigned_to = $request->input('assigned_to');
        $submission->save();

        return back()->with('success', 'Обращение обновлено.');
    }

    public function comment(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], ['body.required' => 'Введите текст комментария.']);

        $submission->comments()->create([
            'user_id' => $request->user()?->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Комментарий добавлен.');
    }

    public function destroy(Submission $submission): RedirectResponse
    {
        $this->authorize('delete', $submission);
        $submission->delete();

        return redirect('/submissions')->with('success', 'Обращение удалено.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Builder<Submission>  $query
     */
    private function applyView(Builder $query, string $view, ?User $user): void
    {
        match ($view) {
            'new' => $query->where('status', SubmissionStatus::New->value),
            'open' => $query->open(),
            'mine' => $query->where('assigned_to', $user?->id),
            'spam' => $query->where('status', SubmissionStatus::Spam->value),
            default => null,
        };
    }

    /**
     * @param  Builder<Submission>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function savedViewCounts(Request $request): array
    {
        $views = [
            ['key' => 'all', 'label' => 'Все обращения'],
            ['key' => 'new', 'label' => 'Новые'],
            ['key' => 'open', 'label' => 'В работе'],
            ['key' => 'mine', 'label' => 'Мои'],
            ['key' => 'spam', 'label' => 'Спам'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = Submission::query();
            $this->applyView($q, $v['key'], $request->user());
            $v['count'] = $q->count();

            return $v;
        }, $views);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function assignees(): array
    {
        return User::query()->role([
            RoleName::Admin->value,
            RoleName::ChiefEditor->value,
            RoleName::Approver->value,
        ])->get()->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->all();
    }
}
