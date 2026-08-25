{{-- Session status, session error and validation errors, in one place so every
     admin screen reports the outcome of a write the same way. --}}
@if (session('status'))
    <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-500/20">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
