@if(session('status'))<div role="status" class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div role="alert" class="mb-6 rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
