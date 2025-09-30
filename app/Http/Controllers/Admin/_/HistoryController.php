<?php

namespace App\Http\Controllers\Admin\_;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class HistoryController extends Controller
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('setting.dblog');
    }

    public function __invoke(Request $request, string $table, string $pk): Response
    {
        $validator = Validator::make(
            ['table' => $table, 'pk' => $pk],
            [
                'table' => ['required', Rule::in(array_keys($this->config['tables']))],
                'pk'    => ['required'],
            ]
        );

        // 如果验证失败，返回错误信息
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $modelClass = getModelByTable($table);

        $object = $modelClass::findOrFail($pk);

        $unions = $this->config['union'][$table] ?? [];
        if ($unions) {
            foreach ($unions as $key => &$union) {
                list($relation_class, $relation_id) = $union;

                $relation_object   = $object->{$relation_class};
                $relation_id_value = $relation_object?->{$relation_id};

                if ($relation_id_value) {
                    $union[] = $relation_id_value;
                } else {
                    unset($unions[$key]);
                }
            }
        }

        $auditSchema = $this->config['schema'];

        // 查询历史记录
        $history = DB::table($auditSchema.'.'.$table)
            ->select(
                '*',
                DB::raw("DATE_TRUNC('second', changed_at) as changed_at"),
                DB::raw(sprintf(" '%s' as tb", $table)),
            )
            ->where('pk', '=', $pk)
            ->when($unions, function (Builder $query) use ($auditSchema, $unions) {
                foreach ($unions as $union) {
                    list($_, $__, $union_table, $union_id) = $union;
                    $sub                                   = DB::table($auditSchema.'.'.$union_table)
                        ->select(
                            '*',
                            DB::raw("DATE_TRUNC('second', changed_at) as changed_at"),
                            DB::raw(sprintf(" '%s' as tb", $union_table))
                        )
                        ->where('pk', '=', $union_id)
                    ;

                    $query->union($sub);
                }
            })
            ->orderBy('log_id', 'desc')
            ->get()
        ;

        $history->map(function (\stdClass $rec) {
            $rec->new_data = $rec->new_data ? json_decode($rec->new_data, true) : null;
            $rec->old_data = $rec->old_data ? json_decode($rec->old_data, true) : null;

            //            /** @var Customer $modelClass */
            //            $modelClass = getModelByTable($rec->tb);
            //
            //                        $changed = array_udiff_assoc($rec->new_data, $rec->old_data, 'shallow_diff');
            //
            //                        $model = (new $modelClass())->fill($changed);
            //
            //                        $rec->changed = $model->toArray();
        });

        $properties = trans('property.'.$model = Str::studly(Str::singular($table)));
        $this->response()->withLang($properties);

        if ($unions) {
            foreach ($unions as $key => $union) {
                list($relation_class, $_, $relation_table) = $union;

                $properties = trans('property.'.$relation_class);
                $this->response()->withLang($properties);

                $model_name = trans('model.'.$relation_class.'.name');
                $this->response()->withLang(['model.'.$relation_table => $model_name]);
            }
        }

        $controller_class = getNamespaceByComposerMap($model.'Controller', 'Admin');

        try {
            $controller_class::{'labelOptions'}($this);
        } catch (\Throwable $e) {
        }

        return $this->response()->withData($history)->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
