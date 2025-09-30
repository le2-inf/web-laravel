<div class="row">
    <div class="col-sm-12 col-md-5">
        全 {{ $data->total() }} 条
        @if($data->total())
            显示： {{ $data->firstItem() }} ～ {{ $data->lastItem() }}
        @endif
    </div>
    <div class="col-sm-12 col-md-7 d-flex flex-row-reverse ">{{ $data->links() }}</div>
</div>
