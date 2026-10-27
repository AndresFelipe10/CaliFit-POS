@if ($paginator->hasPages())
    <nav class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2" role="navigation" aria-label="Paginación de resultados">
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            <ul class="pagination mb-0">
                {{-- Enlace Anterior --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">&laquo; Anterior</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Anterior</a>
                    </li>
                @endif

                {{-- Enlace Siguiente --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente &raquo;</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">Siguiente &raquo;</span>
                    </li>
                @endif
            </ul>
        </div>

        <div class="d-none d-sm-flex align-items-center justify-content-between flex-fill flex-wrap gap-2">
            <div>
                <p class="small text-muted mb-0">
                    Mostrando
                    <span class="fw-semibold text-dark">{{ $paginator->firstItem() }}</span>
                    a
                    <span class="fw-semibold text-dark">{{ $paginator->lastItem() }}</span>
                    de
                    <span class="fw-semibold text-dark">{{ $paginator->total() }}</span>
                    resultados
                </p>
            </div>

            <div>
                <ul class="pagination pagination-sm mb-0">
                    {{-- Enlace Anterior --}}
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="&laquo; Anterior">
                            <span class="page-link" aria-hidden="true">&laquo; Anterior</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="&laquo; Anterior">&laquo; Anterior</a>
                        </li>
                    @endif

                    {{-- Elementos de Paginación --}}
                    @foreach ($elements as $element)
                        {{-- Separador de Puntos Suspensivos --}}
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        {{-- Enlaces Numéricos --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                    <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Enlace Siguiente --}}
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Siguiente &raquo;">Siguiente &raquo;</a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="Siguiente &raquo;">
                            <span class="page-link" aria-hidden="true">Siguiente &raquo;</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif
