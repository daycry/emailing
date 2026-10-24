<?php

namespace App\Livewire;

use App\Services\ActivityService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Activity extends Component
{
    use WithPagination;

    #[Locked]
    public int $accountId;

    #[Locked]
    public ?string $selected = null;

    public string $from = '';

    public string $to = '';

    public string $population = '';

    public string $status = '';

    public string $search = '';

    public string $campaign = '';

    public function mount(ActivityService $activity): void
    {
        $this->accountId = $activity->account();
        $this->from = CarbonImmutable::now('UTC')->subDays(29)->format('Y-m-d');
        $this->to = CarbonImmutable::now('UTC')->format('Y-m-d');
    }

    public function filters(): array
    {
        app(ActivityService::class)->account($this->accountId);

        return $this->only(['from', 'to', 'population', 'status', 'search', 'campaign']);
    }

    public function updated(string $property): void
    {
        app(ActivityService::class)->account($this->accountId);
        $this->resetPage('activityPage');
    }

    public function open(string $key, ActivityService $activity): void
    {
        $activity->detail($key, $this->accountId);
        $this->selected = $key;
        $this->resetPage('attemptsPage');
        $this->dispatch('activity-detail-opened');
    }

    public function close(ActivityService $activity): void
    {
        $activity->account($this->accountId);
        $this->selected = null;
    }

    public function render(ActivityService $activity)
    {
        $activity->account($this->accountId);
        try {
            $filters = $activity->filters($this->filters(), $this->accountId);
            $this->resetValidation(['from', 'to', 'population', 'status', 'search', 'campaign']);
        } catch (ValidationException $error) {
            // Throwing from render gives Livewire no view to attach errors to.
            // Do not query a fallback cohort or show counters for invalid input.
            $this->setErrorBag($error->validator->errors());
            $filters = null;
        }

        return view('livewire.activity', ['validFilters' => $filters !== null,
            'records' => $filters === null ? null : $activity->page($filters, $this->accountId),
            'metrics' => $filters === null ? [] : $activity->metrics($filters, $this->accountId),
            'detail' => $filters === null || $this->selected === null ? null : $activity->detail($this->selected, $this->accountId),
            'attempts' => $filters === null || $this->selected === null ? null : $activity->attempts($this->selected, $this->accountId),
            'states' => ActivityService::STATES, 'populations' => ActivityService::POPULATIONS,
        ])->layout('components.app-layout', ['title' => 'Actividad']);
    }
}
