@props(['workspace' => null])
<label for="name">Nome do espaço financeiro</label>
<input id="name" name="name" value="{{ old('name', $workspace?->name) }}" maxlength="100" required @error('name') aria-invalid="true" aria-describedby="form-errors" @enderror>
<label for="timezone">Fuso horário</label>
<select id="timezone" name="timezone" required @error('timezone') aria-invalid="true" aria-describedby="form-errors" @enderror>
    @foreach (DateTimeZone::listIdentifiers() as $timezone)
        <option value="{{ $timezone }}" @selected(old('timezone', $workspace?->timezone ?? 'America/Sao_Paulo') === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
    @endforeach
</select>
<p class="field-help">Este fuso será usado para as datas do seu espaço financeiro.</p>
