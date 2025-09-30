<?php

namespace App\Http\Middleware;

use App\Attributes\PermissionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, \Closure $next)
    {
        if ($this->checkRequestPermission($request)) {
            return $next($request);
        }
    }

    private function checkRequestPermission(Request $request): bool
    {
        $action = $request->route()->getActionName();

        $actionArray = explode('@', $action);

        if (2 !== sizeof($actionArray)) {
            return true;
        }

        [$controller, $method] = $actionArray;

        // 反射获取方法注解
        $reflectionController = new \ReflectionClass($controller);

        $attributes = $reflectionController->getAttributes(PermissionType::class);

        if (!$attributes) {
            return true;
        }

        $controller_short = preg_replace('{Controller$}', '', class_basename($controller));

        $admin = Auth::user();
        if ($admin && $admin->can($controller_short)) {
            return true;
        }

        abort(403, trans('You have not permission to this page!'));
    }
}
