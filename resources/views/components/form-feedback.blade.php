@if (session('status'))
    <p class="notice" role="status">{{ session('status') }}</p>
@endif
@if ($errors->any())
    <div class="errors" role="alert" id="form-errors" tabindex="-1">
        <p>Revise os dados para continuar:</p>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
