@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'BTC', 'icon' => 'ri-bill-line'])
    <div class="alert alert-warning">This BTC page needs a database update that has not been run on this server yet. A Super Admin can run it from <a href="{{ url('/admin/db-setup') }}">Admin &gt; Database setup</a>.</div>
</div></div>
@endsection
