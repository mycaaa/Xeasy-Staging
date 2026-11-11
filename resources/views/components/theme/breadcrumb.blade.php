@props(['title', 'breadcrumbs' => []])

<div class="row">
   <div class="col-12">
      <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
         <h4 class="mb-sm-0">{{ $title }}</h4>
         @if (!empty($breadcrumbs))
            <div class="page-title-right">
               <ol class="breadcrumb m-0">
                  @foreach ($breadcrumbs as $breadcrumb)
                     @if ($loop->last)
                        <li class="breadcrumb-item active">{{ $breadcrumb['label'] }}</li>
                     @else
                        <li class="breadcrumb-item"><a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a></li>
                     @endif
                  @endforeach
               </ol>
            </div>
         @endif
      </div>
   </div>
</div>
