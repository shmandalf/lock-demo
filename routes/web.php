<?php

use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::prefix('api/tasks')->group(function () {
    Route::post('/create', [TaskController::class, 'create']);
    Route::get('/stats', [TaskController::class, 'stats']);
    Route::post('/clear', [TaskController::class, 'clear']);
    Route::get('/health', [TaskController::class, 'health']);
});

// for debugging purposes
Route::get('/api/tasks/debug-semaphore', function () {
    try {
        $semaphoreKey = 'semaphore:task_processing';
        $currentTime = microtime(true);
        $timeout = 10; // timeout из семафора

        // Получаем все записи
        $allEntries = \Redis::zrange($semaphoreKey, 0, -1, 'WITHSCORES');

        // Очищаем просроченные
        \Redis::zremrangebyscore($semaphoreKey, 0, $currentTime - $timeout);

        // Получаем актуальные записи после очистки
        $currentEntries = \Redis::zrange($semaphoreKey, 0, -1, 'WITHSCORES');
        $activeCount = count($currentEntries) / 2;

        // Детальная информация
        $details = [];
        foreach (array_chunk($currentEntries, 2) as $chunk) {
            if (count($chunk) === 2) {
                $identifier = $chunk[0];
                $timestamp = (float)$chunk[1];
                $age = round($currentTime - $timestamp, 2);
                $details[] = [
                    'identifier' => $identifier,
                    'timestamp' => $timestamp,
                    'age_seconds' => $age,
                    'expires_in' => max(0, $timeout - $age),
                ];
            }
        }

        return response()->json([
            'semaphore_key' => $semaphoreKey,
            'active_locks' => $activeCount,
            'max_concurrent' => 2,
            'available_slots' => max(0, 2 - $activeCount),
            'is_over_limit' => $activeCount > 2,
            'details' => $details,
            'raw_entries_before_cleanup' => $allEntries,
            'cleaned_entries' => (count($allEntries) / 2) - $activeCount,
            'current_time' => $currentTime,
            'timeout_seconds' => $timeout,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'active_locks' => 0
        ]);
    }
});
