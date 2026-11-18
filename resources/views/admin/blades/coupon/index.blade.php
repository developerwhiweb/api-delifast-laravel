@extends('admin.core.admin')
@section('content')

    @includeIf("admin.templates.{$themeData->slug}.{$themeData->template_variation}.coupun.form", [
        'textareaId' => 'textarea-create-persona',
        'serviceSection',
        'serviceItem',
        'themeData',
        'forYou' => 'persona'
    ])
@endsection
