<div class="field-group"><label for="start">Data inicial</label><input class="form-control" type="date" id="start" name="start" value="{{ request('start') }}" required></div>
<div class="field-group"><label for="end">Data final</label><input class="form-control" type="date" id="end" name="end" value="{{ request('end') }}" required></div>

@include('rbl.group-filter')
