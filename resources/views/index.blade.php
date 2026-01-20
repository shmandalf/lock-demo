<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semaphore Lock Demo</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Стили для адаптивной высоты без скролла */
        .screen-height-fit {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }

        .flex-grow-1 {
            flex: 1 1 0%;
            min-height: 0;
        }

        .flex-shrink-0 {
            flex-shrink: 0;
        }

        /* Маленькие квадратики */
        .task {
            width: 16px !important;
            height: 16px !important;
            font-size: 0.5rem !important;
            border-radius: 2px !important;
        }

        /* Уменьшенный шрифт в логах */
        .log-entry {
            font-size: 0.7rem !important;
            line-height: 1.2 !important;
        }

        .log-time {
            font-size: 0.65rem !important;
        }

        /* Уменьшенные легенды */
        .legend-color {
            width: 12px !important;
            height: 12px !important;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-900 to-teal-400 screen-height-fit flex flex-col">
    <div class="flex-grow-1 overflow-hidden p-3">
        <div class="max-w-7xl mx-auto h-full flex flex-col">
            <!-- Header -->
            <div class="bg-white/95 rounded-xl shadow-xl p-4 md:p-6 mb-3 border border-gray-200 flex-shrink-0">
                <div class="text-center">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">
                        <span class="inline-block mr-2">🔒</span>
                        Semaphore Lock Demonstration
                    </h1>
                    <p class="text-gray-600">
                        Real-time visualization of concurrent task processing with Redis semaphores
                    </p>
                </div>
            </div>

            <!-- Основной контейнер -->
            <div class="flex-grow-1 flex flex-col min-h-0">
                <!-- Pipeline и легенда -->
                <div class="flex-grow-1 flex flex-col min-h-0">
                    <!-- Pipeline Container -->
                    <div class="bg-white/95 rounded-xl shadow-xl p-3 md:p-4 mb-3 border border-gray-200 flex-grow-1 min-h-0" style="height: 30vh;">
                        <div class="pipeline-container bg-gray-50 border border-gray-300 rounded-lg p-2 md:p-3 relative h-full" id="pipeline">
                            <!-- Section backgrounds -->
                            <div class="absolute inset-0 flex">
                                <div class="w-1/5 bg-blue-100/30"></div>
                                <div class="w-1/5 bg-orange-100/30"></div>
                                <div class="w-1/5 bg-yellow-100/30"></div>
                                <div class="w-1/5 bg-green-100/30"></div>
                                <div class="w-1/5 bg-emerald-100/30"></div>
                            </div>

                            <!-- Section dividers -->
                            <div class="section-divider left-1/5"></div>
                            <div class="section-divider left-2/5"></div>
                            <div class="section-divider left-3/5 bg-red-500"></div>
                            <div class="section-divider left-4/5"></div>

                            <!-- Main pipeline line -->
                            <div class="pipeline-line"></div>
                            <div class="pipeline-mid"></div>

                            <!-- Labels (центры секций) -->
                            <div class="pipeline-mid-label absolute top-2 left-1/2 transform -translate-x-1/2 bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold z-15">
                                LOCK CHECK
                            </div>

                            <!-- Заголовки в центрах секций -->
                            <div class="stage-label left-[10%]">QUEUE</div>
                            <div class="stage-label left-[30%]">PROCESS</div>
                            <div class="stage-label left-[70%]">IN PROGRESS</div>
                            <div class="stage-label left-[90%]">COMPLETE</div>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="bg-white/95 rounded-xl shadow-xl p-3 md:p-4 mb-3 border border-gray-200 flex-shrink-0">
                        <div class="flex flex-wrap justify-center gap-2">
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-blue"></div>
                                <span class="text-xs">Queued</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-orange"></div>
                                <span class="text-xs">Processing</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-yellow"></div>
                                <span class="text-xs">Checking Lock</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-green"></div>
                                <span class="text-xs">Lock Acquired</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-red"></div>
                                <span class="text-xs">Lock Failed</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Нижняя часть: Stats, Controls, Logs -->
                <div class="flex-grow-1 flex flex-col min-h-0">
                    <!-- Stats и Controls в одной строке -->
                    <div class="flex flex-col md:flex-row gap-3 mb-3 flex-shrink-0">
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-2 md:gap-3 md:w-2/3">
                            <div class="stat-card rounded-xl shadow-lg p-3 md:p-4">
                                <div class="text-center">
                                    <div class="text-gray-300 text-sm mb-1">ACTIVE TASKS</div>
                                    <div class="stat-value text-white text-lg md:text-xl" id="activeTasks">0</div>
                                </div>
                            </div>
                            <div class="stat-card rounded-xl shadow-lg p-3 md:p-4">
                                <div class="text-center">
                                    <div class="text-gray-300 text-sm mb-1">ACTIVE LOCKS</div>
                                    <div class="stat-value text-white text-lg md:text-xl" id="activeLocks">0</div>
                                </div>
                            </div>
                            <div class="stat-card rounded-xl shadow-lg p-3 md:p-4">
                                <div class="text-center">
                                    <div class="text-gray-300 text-sm mb-1">COMPLETED</div>
                                    <div class="stat-value text-white text-lg md:text-xl" id="completedTasks">0</div>
                                </div>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="bg-white/95 rounded-xl shadow-xl p-3 md:p-4 border border-gray-200 md:w-1/3">
                            <div class="flex flex-col gap-2">
                                <div class="flex gap-2">
                                    <button class="flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors shadow hover:shadow-lg text-sm flex-1" id="createSingleBtn">
                                        <span>+</span>
                                        <span>Single Task</span>
                                    </button>
                                    <button class="flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors shadow hover:shadow-lg text-sm flex-1" id="createThreeBtn">
                                        <span>🚀</span>
                                        <span>3 Tasks</span>
                                    </button>
                                    <button class="flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors shadow hover:shadow-lg text-sm flex-1" id="createFiveBtn">
                                        <span>🔥</span>
                                        <span>5 Tasks</span>
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <div class="mb-1 flex justify-between text-sm text-gray-600">
                                        <span>Queue Load</span>
                                        <span id="queueLoad">0%</span>
                                    </div>
                                    <div class="progress-thin bg-gray-200 rounded-full overflow-hidden h-1.5">
                                        <div class="progress-fill h-full bg-gradient-to-r from-blue-500 to-green-500 transition-all duration-300" id="queueProgress" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logs - 1/5 экрана -->
                    <div class="flex-grow-1 min-h-0" style="height: 20vh;">
                        <div class="bg-white/95 rounded-xl shadow-xl border border-gray-200 overflow-hidden h-full">
                            <div class="bg-gray-800 text-white px-4 py-2 border-b border-gray-700">
                                <div class="flex justify-between items-center">
                                    <h3 class="font-medium">
                                        <span class="inline-block mr-2">📋</span>
                                        Event Log
                                    </h3>
                                    <span class="text-gray-400 text-sm">Live WebSocket Events</span>
                                </div>
                            </div>
                            <div class="log-panel bg-black text-green-400 p-2 overflow-y-auto h-full" id="logPanel">
                                <div class="log-entry border-b border-gray-800 pb-1 mb-1 text-xs">
                                    <span class="log-time text-blue-400">[00:00:00]</span>
                                    <span>System initialized. Waiting for tasks...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Глобальные переменные
            let tasks = {};
            let activeTasks = 0;
            let completedCount = 0;
            let pusher = null;
            let channel = null;
            const csrfToken = '{{ csrf_token() }}';

            // Проверяем что Pusher загружен
            if (typeof window.Pusher === 'undefined') {
                console.error('Pusher not loaded');
                addLog('SYSTEM', 'error', 1, 'WebSocket library not loaded');
                return;
            }

            // Инициализируем Pusher
            try {
                pusher = new window.Pusher('app-key', {
                    wsHost: window.location.hostname,
                    wsPort: 6001,
                    wssPort: 6001,
                    forceTLS: false,
                    encrypted: false,
                    disableStats: true,
                    cluster: 'mt1',
                });

                // Подписываемся на канал
                channel = pusher.subscribe('tasks');

                channel.bind('task.status.changed', function(data) {
                    handleTaskUpdate(data);
                });

            } catch (error) {
                console.error('Pusher error:', error);
                addLog('SYSTEM', 'error', 1, 'WebSocket initialization failed');
            }

            // Обработчики кнопок
            document.getElementById('createSingleBtn').addEventListener('click', () => createTask(1));
            document.getElementById('createThreeBtn').addEventListener('click', () => createTask(3));
            document.getElementById('createFiveBtn').addEventListener('click', () => createTask(5));

            // Функция обработки обновления задачи
            function handleTaskUpdate(data) {
                const taskId = data.taskId || 'unknown-' + Date.now();
                const status = data.status || 'unknown';
                const attempt = data.attempt || 1;
                const message = data.data?.message || '';

                addLog(taskId, status, attempt, message);

                if (!tasks[taskId]) {
                    createTaskElement(taskId, status, attempt);
                }

                updateTaskElement(taskId, status, attempt, data.data || {});
                updateStats();
            }

            // Функция создания элемента задачи
            function createTaskElement(taskId, status = 'queued', attempt = 1) {
                const pipeline = document.getElementById('pipeline');
                if (!pipeline) return;

                const oldElement = document.getElementById(`task-${taskId}`);
                if (oldElement) oldElement.remove();

                const task = document.createElement('div');
                task.className = `task task-pulse task-${getStatusClass(status)}`;
                task.id = `task-${taskId}`;
                task.dataset.taskId = taskId;
                task.textContent = attempt;

                // Получаем секцию для текущего статуса
                const section = getSectionForStatus(status);

                // Случайное вертикальное позиционирование
                const minTop = 15;
                const maxTop = 85;
                let topPos;

                // Случайное горизонтальное смещение ВНУТРИ секции
                const sectionWidth = 18;
                const sectionStart = section.sectionIndex * 20;
                const horizontalMargin = 1;
                const minLeft = sectionStart + horizontalMargin;
                const maxLeft = sectionStart + sectionWidth - horizontalMargin;

                let leftPos, attempts = 0;

                do {
                    topPos = minTop + Math.random() * (maxTop - minTop);
                    leftPos = minLeft + Math.random() * (maxLeft - minLeft);
                    attempts++;

                    if (attempts > 50) {
                        console.warn(`Could not find free position for task ${taskId} after ${attempts} attempts`);
                        break;
                    }
                } while (isPositionOccupied(leftPos, topPos, 3, 3));

                task.style.left = `${leftPos}%`;
                task.style.top = `${topPos}%`;
                task.style.transform = 'translateY(-50%)';

                pipeline.appendChild(task);

                // Сохраняем в памяти
                tasks[taskId] = {
                    element: task,
                    status: status,
                    attempt: attempt,
                    position: leftPos,
                    topPosition: topPos,
                    targetPosition: section.center
                };

                activeTasks++;
            }

            // Получаем секцию для статуса
            function getSectionForStatus(status) {
                const sections = {
                    'queued': {
                        sectionIndex: 0,
                        center: 10
                    },
                    'processing': {
                        sectionIndex: 1,
                        center: 30
                    },
                    'checking_lock': {
                        sectionIndex: 2,
                        center: 50
                    },
                    'lock_acquired': {
                        sectionIndex: 3,
                        center: 70
                    },
                    'processing_progress': {
                        sectionIndex: 3,
                        center: 70
                    },
                    'lock_failed': {
                        sectionIndex: 0,
                        center: 10
                    },
                    'completed': {
                        sectionIndex: 4,
                        center: 90
                    },
                    'failed': {
                        sectionIndex: 0,
                        center: 10
                    }
                };

                return sections[status] || sections.queued;
            }

            // Проверка занятой позиции
            function isPositionOccupied(leftPos, topPos, hTolerance = 6, vTolerance = 5) {
                for (const taskId in tasks) {
                    const task = tasks[taskId];
                    const hDiff = Math.abs(task.position - leftPos);
                    const vDiff = Math.abs(task.topPosition - topPos);

                    if (hDiff < hTolerance && vDiff < vTolerance) {
                        return true;
                    }
                }
                return false;
            }

            // Получаем класс статуса
            function getStatusClass(status) {
                switch (status) {
                    case 'queued':
                        return 'queued';
                    case 'processing':
                        return 'processing';
                    case 'checking_lock':
                        return 'checking';
                    case 'lock_acquired':
                        return 'lock';
                    case 'lock_failed':
                        return 'failed';
                    case 'processing_progress':
                        return 'progress';
                    case 'completed':
                        return 'completed';
                    default:
                        return 'queued';
                }
            }

            // Обновление элемента задачи
            function updateTaskElement(taskId, status, attempt, extraData = {}) {
                const task = tasks[taskId];
                if (!task) return;

                const element = task.element;
                if (!element) return;

                task.status = status;
                task.attempt = attempt;

                element.className = 'task task-pulse';
                element.classList.add(`task-${getStatusClass(status)}`);
                element.textContent = attempt;

                const section = getSectionForStatus(status);

                const minTop = 15;
                const maxTop = 85;
                const sectionWidth = 18;
                const sectionStart = section.sectionIndex * 20;
                const horizontalMargin = 1;
                const minLeft = sectionStart + horizontalMargin;
                const maxLeft = sectionStart + sectionWidth - horizontalMargin;

                const newTopPos = minTop + Math.random() * (maxTop - minTop);
                const newLeftPos = minLeft + Math.random() * (maxLeft - minLeft);

                if (status === 'completed' || status === 'failed') {
                    animateTask(element, task.position, section.center, task.topPosition);
                    task.position = section.center;

                    setTimeout(() => {
                        if (element.parentNode) {
                            element.parentNode.removeChild(element);
                            delete tasks[taskId];
                            activeTasks--;
                            if (status === 'completed') completedCount++;
                            updateStats();
                        }
                    }, 800);
                } else {
                    animateTask(element, task.position, newLeftPos, task.topPosition);
                    animateVerticalPosition(element, task.topPosition, newTopPos);
                    task.position = newLeftPos;
                    task.topPosition = newTopPos;
                }

                setTimeout(() => {
                    element.classList.remove('task-pulse');
                }, 300);
            }

            // Анимация вертикального движения
            function animateVerticalPosition(element, from, to) {
                const duration = 400;
                const startTime = performance.now();

                function step(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);

                    const easeProgress = progress < 0.5 ?
                        2 * progress * progress :
                        1 - Math.pow(-2 * progress + 2, 2) / 2;

                    const currentPos = from + (to - from) * easeProgress;
                    element.style.top = `${currentPos}%`;

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    }
                }

                requestAnimationFrame(step);
            }

            // Анимация движения
            function animateTask(element, from, to, topPosition) {
                const duration = 600;
                const startTime = performance.now();

                function step(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);

                    const easeProgress = progress < 0.5 ?
                        2 * progress * progress :
                        1 - Math.pow(-2 * progress + 2, 2) / 2;

                    const currentPos = from + (to - from) * easeProgress;
                    element.style.left = `${currentPos}%`;

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    }
                }

                requestAnimationFrame(step);
            }

            // Обновление статистики
            function updateStats() {
                document.getElementById('activeTasks').textContent = activeTasks;
                document.getElementById('completedTasks').textContent = completedCount;

                let activeLocks = 0;
                for (const taskId in tasks) {
                    const task = tasks[taskId];
                    if (task.status === 'lock_acquired' || task.status === 'processing_progress') {
                        activeLocks++;
                    }
                }
                document.getElementById('activeLocks').textContent = activeLocks;

                const maxConcurrent = 2;
                const queueLoad = Math.min((activeTasks / maxConcurrent) * 100, 100);
                document.getElementById('queueProgress').style.width = `${queueLoad}%`;
                document.getElementById('queueLoad').textContent = `${Math.round(queueLoad)}%`;
            }

            // Добавление лога
            function addLog(taskId, status, attempt, message = '') {
                const logPanel = document.getElementById('logPanel');
                const time = new Date().toLocaleTimeString();
                const shortTaskId = taskId ? taskId.substring(0, 6) : 'SYS';

                const logEntry = document.createElement('div');
                logEntry.className = 'log-entry border-b border-gray-800 pb-1 mb-1 text-xs';
                logEntry.innerHTML = `
                    <span class="log-time text-blue-400">[${time}]</span>
                    <span class="text-yellow-300 ml-1">${shortTaskId}</span>
                    <span class="text-green-400 ml-1">${status.toUpperCase()}</span>
                    ${message ? `<span class="ml-1">- ${message}</span>` : ''}
                `;

                logPanel.appendChild(logEntry);
                logPanel.scrollTop = logPanel.scrollHeight;

                if (logPanel.children.length > 40) {
                    logPanel.removeChild(logPanel.firstChild);
                }
            }

            // Создание задач
            function createTask(count = 1) {
                if (!csrfToken) {
                    addLog('SYSTEM', 'error', 1, 'CSRF token not found');
                    return;
                }

                fetch('/api/tasks/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            count
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        addLog('SYSTEM', 'info', 1, `Created ${count} task(s)`);
                    })
                    .catch(error => {
                        addLog('SYSTEM', 'error', 1, 'Failed to create task');
                    });
            }

            // Инициализация
            updateStats();

            // Периодическое обновление статистики
            setInterval(updateStats, 2000);

            // Начальное сообщение
            setTimeout(() => {
                addLog('SYSTEM', 'info', 1, 'Ready to create tasks');
            }, 500);
        });
    </script>
</body>

</html>