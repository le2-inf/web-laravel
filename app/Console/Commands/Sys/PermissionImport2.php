<?php

namespace App\Console\Commands\Sys;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_sys:permission:import2',
    description: ''
)]
class PermissionImport2 extends Command
{
    protected $signature   = '_sys:permission:import2';
    protected $description = '';

    public function handle(): int
    {
        Artisan::call('permission:cache-reset');

        $controllers = $this->getAllControllers();

        DB::transaction(function () use ($controllers) {
            $permissionNames = Permission::query()->lockForUpdate()->get()->pluck('', 'name')->keys()->toArray();

            foreach ($controllers as $name) {
                //                $name  = join('.', $as);
                $title = trans_controller($name);

                Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['title' => $title]
                );

                if (($key = array_search($name, $permissionNames)) !== false) {
                    unset($permissionNames[$key]);
                }
            }

            Permission::query()->whereIn('name', $permissionNames)->delete();
        });

        return CommandAlias::SUCCESS;
    }

    private function getAllControllers(): array
    {
        $routeCollection = Route::getRoutes();

        $names = [];
        foreach ($routeCollection as $route) {
            /**
             * @var \Illuminate\Routing\Route $route
             */
            $controllerClass = $route->getControllerClass();

            if (!$controllerClass) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($controllerClass);

            $attributes = $reflectionClass->getAttributes(PermissionType::class);

            if (!$attributes) {
                continue;
            }

            $names[] = preg_replace('{Controller$}', '', class_basename($controllerClass));

            //            $uses = $action['uses'];
            //
            //            if (!is_string($uses)) {
            //                continue;
            //            }
            //
            //            $reflectionMethod = null;
            //
            //            try {
            //                $reflectionMethod = new \ReflectionMethod(str_replace('@', '::', $uses));
            //            } catch (\ReflectionException $e) {
            //                logger()->error($e->getMessage());
            //
            //                continue;
            //            }
            //
            //            $class = $reflectionMethod->getDeclaringClass()->getName();
            //
            //            $attributes = $reflectionMethod->getAttributes(PermissionAction::class);
            //
            //            if (!$attributes) {
            //                continue;
            //            }
            //
            //            // 假设一个方法只有一个 Permission 属性
            //            /** @var PermissionAction $permissionAttribute */
            //            $permissionAttribute = $attributes[0]->newInstance();
            //
            //            $permissionMethodName = $permissionAttribute->name;
            //
            //            $names[] = [Str::kebab(preg_replace('{Controller$}', '', class_basename($class))), $permissionMethodName];
        }

        return array_unique($names);
    }
}
