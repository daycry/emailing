@props(['paginator', 'label'])
@if($paginator->hasPages())
<nav aria-label="{{ $label }}" class="flex flex-wrap items-center justify-between gap-3 mt-4" wire:key="campaign-pagination-{{ $paginator->getPageName() }}">
    <p class="text-xs text-slate-500">Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}</p>
    <div class="flex gap-2">
        <button type="button" class="btn-secondary disabled:opacity-50 focus-visible:outline-2" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled($paginator->onFirstPage())>Anterior</button>
        <button type="button" class="btn-secondary disabled:opacity-50 focus-visible:outline-2" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled(!$paginator->hasMorePages())>Siguiente</button>
    </div>
</nav>
@endif
