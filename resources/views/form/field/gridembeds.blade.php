
@if($enableHeader)
<div class="">
    <h4 class="field-header">{{ $label }}</h4>
</div>
<hr style="margin-top: 0px;">
@endif

<div id="embed-{{$column}}" class="embed-{{$column}}">

    <div class="embed-{{$column}}-forms">

        <div class="embed-{{$column}}-form fields-group">
            @foreach($fieldGroups as $fieldRow)
                <div class="row">
                    @foreach($fieldRow['columns'] as $fieldColumn)
                        <div class="col-sm-{{array_get($fieldColumn, 'col_sm', 12)}}">
                        @foreach($fieldColumn['fields'] as $field)
                            <div class="row"><div class="col-sm-{{array_get($field, 'field_sm', 12)}} col-sm-offset-{{array_get($field, 'field_offset', 0)}}">
                            {!! $field['field']->render() !!}
                            </div></div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            @endforeach

        </div>
    </div>
</div>

@if($footer_hr)
<hr style="margin-top: 0px;">
@endif