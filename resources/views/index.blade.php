<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semaphore Lock Demo</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Global styles for responsive height without scroll */
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

        /* Task squares styling - NO BLINKING! */
        .task {
            width: 16px !important;
            height: 16px !important;
            font-size: 0.6rem !important;
            border-radius: 2px !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white !important;
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.3);
            transition: left 0.6s ease, opacity 0.6s ease;
            animation: none !important;
            /* NO BLINKING */
            position: absolute;
            z-index: 10;
        }

        /* Color scheme for different max_concurrent values (1-10) */
        .task-concurrent-1 {
            background-color: #3B82F6 !important;
        }

        /* blue-500 */
        .task-concurrent-2 {
            background-color: #10B981 !important;
        }

        /* emerald-500 */
        .task-concurrent-3 {
            background-color: #F59E0B !important;
        }

        /* amber-500 */
        .task-concurrent-4 {
            background-color: #EF4444 !important;
        }

        /* red-500 */
        .task-concurrent-5 {
            background-color: #8B5CF6 !important;
        }

        /* violet-500 */
        .task-concurrent-6 {
            background-color: #EC4899 !important;
        }

        /* pink-500 */
        .task-concurrent-7 {
            background-color: #06B6D4 !important;
        }

        /* cyan-500 */
        .task-concurrent-8 {
            background-color: #84CC16 !important;
        }

        /* lime-500 */
        .task-concurrent-9 {
            background-color: #F97316 !important;
        }

        /* orange-500 */
        .task-concurrent-10 {
            background-color: #6366F1 !important;
        }

        /* indigo-500 */

        /* Status styling over concurrent colors - NO ANIMATIONS */
        .task.queued {
            opacity: 0.8;
        }

        .task.processing {
            opacity: 0.9;
        }

        .task.checking {
            opacity: 0.9;
        }

        .task.lock {
            opacity: 1;
        }

        .task.progress {
            opacity: 1;
        }

        .task.failed {
            opacity: 0.6;
            border: 1px dashed #EF4444 !important;
        }

        .task.completed {
            opacity: 1;
            border: 2px solid white !important;
        }

        /* Only keep the appearance animation - NO BLINKING */
        .task-pulse {
            animation: task-appear 0.3s ease-out;
        }

        @keyframes task-appear {
            from {
                transform: translateY(-50%) scale(0);
                opacity: 0;
            }

            to {
                transform: translateY(-50%) scale(1);
                opacity: 1;
            }
        }

        /* Reduced font size in logs */
        .log-entry {
            font-size: 0.7rem !important;
            line-height: 1.2 !important;
        }

        .log-time {
            font-size: 0.65rem !important;
        }

        /* Combined main section (pipeline + controls) */
        .main-section {
            background: white;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            overflow: hidden;
            height: 55vh;
        }

        /* Pipeline part - REDUCED HEIGHT */
        .pipeline-part {
            height: 35vh;
            position: relative;
            background: #F9FAFB;
        }

        /* Controls part */
        .controls-part {
            background: white;
            padding: 20px;
            border-top: 1px solid #E5E7EB;
        }

        .slider-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .slider-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #4B5563;
            min-width: 120px;
        }

        .slider-value {
            min-width: 40px;
            text-align: center;
            font-weight: bold;
            color: white;
            background: #3B82F6;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .max-concurrent-slider {
            flex-grow: 1;
            height: 6px;
            -webkit-appearance: none;
            background: #D1D5DB;
            border-radius: 3px;
            outline: none;
        }

        .max-concurrent-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 22px;
            height: 22px;
            background: #3B82F6;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .max-concurrent-slider::-moz-range-thumb {
            width: 22px;
            height: 22px;
            background: #3B82F6;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        /* Task creation buttons - normal size */
        .task-buttons-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .task-button {
            padding: 8px 4px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            outline: none;
            background-color: #3B82F6;
            color: white;
            border: 1px solid #2563EB;
            font-size: 0.85rem;
            height: 36px;
            text-align: center;
        }

        .task-button:hover {
            background-color: #2563EB;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        .task-button:active {
            transform: translateY(0);
            background-color: #1D4ED8;
        }

        /* Settings info in one line */
        .settings-info {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            font-size: 0.75rem;
            color: #6B7280;
            padding: 8px 12px;
            background: #F9FAFB;
            border-radius: 6px;
            border: 1px solid #E5E7EB;
            margin-top: 10px;
        }

        .setting-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .setting-dot {
            width: 6px;
            height: 6px;
            background-color: #3B82F6;
            border-radius: 50%;
        }

        .setting-value {
            font-weight: 600;
            color: #111827;
        }

        /* Success popup */
        .success-popup {
            position: fixed;
            top: 100px;
            right: 30px;
            background: #10B981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            font-weight: 500;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s, transform 0.3s;
        }

        .success-popup.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Event log - larger */
        .log-container {
            height: 35vh;
            min-height: 250px;
            background: white;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .log-header {
            background: #374151;
            color: white;
            padding: 12px 16px;
            border-bottom: 1px solid #4B5563;
        }

        .log-header h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .log-header span {
            font-size: 0.75rem;
            color: #9CA3AF;
        }

        .log-content {
            flex-grow: 1;
            background: black;
            color: #10B981;
            padding: 12px;
            overflow-y: auto;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.8rem;
        }

        /* Section backgrounds */
        .section-bg-1 {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .section-bg-2 {
            background-color: rgba(251, 191, 36, 0.1);
        }

        .section-bg-3 {
            background-color: rgba(250, 204, 21, 0.1);
        }

        .section-bg-4 {
            background-color: rgba(34, 197, 94, 0.1);
        }

        .section-bg-5 {
            background-color: rgba(16, 185, 129, 0.1);
        }

        /* Vertical dividers */
        .divider {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #D1D5DB;
            z-index: 5;
        }

        .divider-1 {
            left: 20%;
        }

        .divider-2 {
            left: 40%;
        }

        .divider-3 {
            left: 60%;
            background-color: #EF4444;
            width: 3px;
        }

        .divider-4 {
            left: 80%;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-900 to-teal-400 screen-height-fit flex flex-col">
    <!-- Success popup (hidden by default) -->
    <div class="success-popup" id="successPopup"></div>

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

            <!-- Main section: Pipeline + Controls -->
            <div class="main-section">
                <!-- Pipeline part - REDUCED HEIGHT -->
                <div class="pipeline-part relative" id="pipeline">
                    <!-- Section backgrounds -->
                    <div class="absolute inset-0 flex">
                        <div class="w-1/5 section-bg-1"></div>
                        <div class="w-1/5 section-bg-2"></div>
                        <div class="w-1/5 section-bg-3"></div>
                        <div class="w-1/5 section-bg-4"></div>
                        <div class="w-1/5 section-bg-5"></div>
                    </div>

                    <!-- Vertical dividers -->
                    <div class="divider divider-1"></div>
                    <div class="divider divider-2"></div>
                    <div class="divider divider-3"></div>
                    <div class="divider divider-4"></div>

                    <!-- Main pipeline line -->
                    <div class="absolute top-1/2 left-[10%] right-[10%] h-px bg-gradient-to-r from-blue-500 to-green-500 transform -translate-y-1/2 z-1"></div>

                    <!-- Middle circle -->
                    <div class="absolute top-1/2 left-[60%] w-8 h-8 bg-red-500 rounded-full transform -translate-x-1/2 -translate-y-1/2 border-4 border-white shadow-lg z-10"></div>

                    <!-- Lock check label -->
                    <div class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-red-500 text-white px-3 py-1 rounded-full text-xs font-bold z-20">
                        LOCK CHECK
                    </div>

                    <!-- Section titles -->
                    <div class="absolute top-1/2 left-[10%] transform -translate-x-1/2 -translate-y-1/2 text-sm font-semibold text-gray-700 z-5">QUEUE</div>
                    <div class="absolute top-1/2 left-[30%] transform -translate-x-1/2 -translate-y-1/2 text-sm font-semibold text-gray-700 z-5">PROCESS</div>
                    <div class="absolute top-1/2 left-[70%] transform -translate-x-1/2 -translate-y-1/2 text-sm font-semibold text-gray-700 z-5">IN PROGRESS</div>
                    <div class="absolute top-1/2 left-[90%] transform -translate-x-1/2 -translate-y-1/2 text-sm font-semibold text-gray-700 z-5">COMPLETE</div>

                    <!-- Tasks will appear here -->
                </div>

                <!-- Controls part -->
                <div class="controls-part">
                    <!-- Slider -->
                    <div class="slider-controls">
                        <span class="slider-label">Max Concurrent Tasks:</span>
                        <input type="range" id="maxConcurrentSlider" class="max-concurrent-slider" min="1" max="10" value="2">
                        <div class="slider-value" id="maxConcurrentDisplay">2</div>
                    </div>

                    <!-- Task creation buttons -->
                    <div class="task-buttons-container">
                        <button class="task-button" id="createOneBtn">1 task</button>
                        <button class="task-button" id="createFiveBtn">5 tasks</button>
                        <button class="task-button" id="createTwentyBtn">20 tasks</button>
                        <button class="task-button" id="createFiftyBtn">50 tasks</button>
                        <button class="task-button" id="createHundredBtn">100 tasks</button>
                    </div>

                    <!-- Settings info in one line -->
                    <div class="settings-info">
                        <div class="setting-item">
                            <div class="setting-dot"></div>
                            <span>Semaphore acquire timeout:</span>
                            <span class="setting-value ml-1">3s</span>
                        </div>
                        <div class="text-gray-300">•</div>
                        <div class="setting-item">
                            <div class="setting-dot"></div>
                            <span>Task processing:</span>
                            <span class="setting-value ml-1">4s</span>
                        </div>
                        <div class="text-gray-300">•</div>
                        <div class="setting-item">
                            <div class="setting-dot"></div>
                            <span>Semaphore TTL:</span>
                            <span class="setting-value ml-1">5s</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event log - larger -->
            <div class="log-container flex-grow-1">
                <div class="log-header">
                    <div class="flex justify-between items-center">
                        <h3>
                            <span class="inline-block mr-2">📋</span>
                            Event Log
                        </h3>
                        <span>Live WebSocket Events</span>
                    </div>
                </div>
                <div class="log-content" id="logPanel">
                    <div class="log-entry border-b border-gray-800 pb-2 mb-2 text-xs">
                        <span class="log-time text-blue-400">[00:00:00]</span>
                        <span>System initialized. Waiting for tasks...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Global variables
            let tasks = {};
            let pusher = null;
            let channel = null;
            const csrfToken = '{{ csrf_token() }}';

            // Current max concurrent value for new tasks
            let currentMaxConcurrent = 2;

            // Check if Pusher is loaded
            if (typeof window.Pusher === 'undefined') {
                console.error('Pusher not loaded');
                addLog('SYSTEM', 'error', 1, 'WebSocket library not loaded');
                return;
            }

            // Initialize Pusher
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

                // Subscribe to channel
                channel = pusher.subscribe('tasks');

                channel.bind('task.status.changed', function(data) {
                    handleTaskUpdate(data);
                });

            } catch (error) {
                console.error('Pusher error:', error);
                addLog('SYSTEM', 'error', 1, 'WebSocket initialization failed');
            }

            // Slider for max concurrent
            const maxConcurrentSlider = document.getElementById('maxConcurrentSlider');
            const maxConcurrentDisplay = document.getElementById('maxConcurrentDisplay');

            maxConcurrentSlider.addEventListener('input', function() {
                currentMaxConcurrent = parseInt(this.value);
                maxConcurrentDisplay.textContent = currentMaxConcurrent;
                addLog('SYSTEM', 'config', 1, `Max concurrent for new tasks set to ${currentMaxConcurrent}`);
            });

            // Button handlers for task creation
            document.getElementById('createOneBtn').addEventListener('click', () => createTask(1));
            document.getElementById('createFiveBtn').addEventListener('click', () => createTask(5));
            document.getElementById('createTwentyBtn').addEventListener('click', () => createTask(20));
            document.getElementById('createFiftyBtn').addEventListener('click', () => createTask(50));
            document.getElementById('createHundredBtn').addEventListener('click', () => createTask(100));

            // Function to show success popup
            function showSuccessPopup(message) {
                const popup = document.getElementById('successPopup');
                popup.textContent = message;
                popup.classList.add('show');

                setTimeout(() => {
                    popup.classList.remove('show');
                }, 2000);
            }

            // Function to handle task update
            function handleTaskUpdate(data) {
                const taskId = data.taskId || 'unknown-' + Date.now();
                const status = data.status || 'unknown';
                const attempt = data.attempt || 1;
                const message = data.data?.message || '';
                const progress = data.data?.progress || 0;
                // Get max_concurrent from extraData
                const maxConcurrentValue = data.data?.extraData?.max_concurrent || currentMaxConcurrent;

                addLog(taskId, status, attempt, message);

                if (!tasks[taskId]) {
                    createTaskElement(taskId, status, attempt, maxConcurrentValue);
                }

                updateTaskElement(taskId, status, attempt, maxConcurrentValue, {
                    ...data.data || {},
                    progress: progress
                });
            }

            // Function to create task element
            function createTaskElement(taskId, status = 'queued', attempt = 1, taskMaxConcurrent = currentMaxConcurrent) {
                const pipeline = document.getElementById('pipeline');
                if (!pipeline) return;

                const oldElement = document.getElementById(`task-${taskId}`);
                if (oldElement) oldElement.remove();

                const task = document.createElement('div');
                task.className = `task task-pulse task-${getStatusClass(status)} task-concurrent-${taskMaxConcurrent}`;
                task.id = `task-${taskId}`;
                task.dataset.taskId = taskId;
                task.dataset.maxConcurrent = taskMaxConcurrent;

                // ALWAYS show max_concurrent value
                task.textContent = taskMaxConcurrent;

                // Get section for current status
                const section = getSectionForStatus(status);

                // Random fixed vertical position (15-85%)
                const topPos = 15 + Math.random() * 70;

                // For COMPLETE section: random horizontal position between 80-95%
                let leftPos;
                if (status === 'completed') {
                    leftPos = 80 + Math.random() * 15; // 80% to 95%
                } else {
                    // Fixed horizontal position in section center with small jitter
                    const horizontalJitter = Math.random() * 4 - 2;
                    leftPos = section.center + horizontalJitter;
                }

                task.style.left = `${leftPos}%`;
                task.style.top = `${topPos}%`;
                task.style.transform = 'translateY(-50%)';

                pipeline.appendChild(task);

                // Save in memory
                tasks[taskId] = {
                    element: task,
                    status: status,
                    attempt: attempt,
                    maxConcurrent: taskMaxConcurrent,
                    position: leftPos,
                    topPosition: topPos,
                    targetPosition: section.center,
                    verticalPosition: topPos,
                    currentSection: section.sectionIndex,
                    progress: 0
                };
            }

            // Get section for status - FIXED: 'completed' now goes to section 4
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
                        center: 70 // Stays in IN PROGRESS section
                    },
                    'completed': {
                        sectionIndex: 4, // MOVES TO COMPLETE SECTION
                        center: 90 // Center position (will be overridden with random)
                    },
                    'lock_failed': {
                        sectionIndex: 0,
                        center: 10
                    },
                    'failed': {
                        sectionIndex: 0,
                        center: 10
                    }
                };

                return sections[status] || sections.queued;
            }

            // Get status class - NO ANIMATIONS
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
                    case 'failed':
                        return 'failed';
                    default:
                        return 'queued';
                }
            }

            // Update task element - ALWAYS show max_concurrent from original value
            function updateTaskElement(taskId, status, attempt, taskMaxConcurrent, extraData = {}) {
                const task = tasks[taskId];
                if (!task) return;

                // FIX: Ignore processing_progress if task is already completed
                if (task.status === 'completed' && status === 'processing_progress') {
                    console.log(`Ignoring processing_progress for already completed task: ${taskId}`);
                    return;
                }

                const element = task.element;
                if (!element) return;

                const oldStatus = task.status;
                task.status = status;
                task.attempt = attempt;

                // IMPORTANT: Do NOT update max_concurrent for existing tasks
                // Keep the original value that was set when task was created
                // task.maxConcurrent remains unchanged

                // Update progress if available
                if (extraData.progress !== undefined) {
                    task.progress = extraData.progress;
                }

                // Clear old concurrent classes
                element.className = 'task task-pulse';

                // Add base class for status - NO ANIMATIONS
                element.classList.add(`task-${getStatusClass(status)}`);

                // Add color class for max_concurrent (limit 1-10) using original value
                const concurrentClassValue = Math.max(1, Math.min(10, task.maxConcurrent));
                element.classList.add(`task-concurrent-${concurrentClassValue}`);

                // ALWAYS show max_concurrent value (original value)
                element.textContent = task.maxConcurrent;

                // Update tooltip with semaphore info
                const statusText = getStatusText(status);
                element.title = `Semaphore: semaphore:${task.maxConcurrent}, Status: ${statusText}, Attempt: ${attempt}`;

                const section = getSectionForStatus(status);
                const oldSection = task.currentSection;
                task.currentSection = section.sectionIndex;

                // Fix task positions
                const currentTopPos = task.topPosition;
                const currentLeftPos = task.position;

                // If task is already in In Progress section (lock_acquired or processing_progress)
                // And transitions to same section - don't move!
                const isInProgressSection = (status === 'lock_acquired' || status === 'processing_progress');
                const wasInProgressSection = (oldStatus === 'lock_acquired' || oldStatus === 'processing_progress');

                if (isInProgressSection && wasInProgressSection) {
                    // Stay in place in In Progress
                    task.position = currentLeftPos;
                } else if (status === 'completed') {
                    // For completed tasks: random horizontal position in COMPLETE section (80-95%)
                    const randomLeft = 80 + Math.random() * 15;
                    animateHorizontalTask(element, currentLeftPos, randomLeft, currentTopPos);
                    task.position = randomLeft;

                    // Make completed tasks more transparent
                    setTimeout(() => {
                        element.style.opacity = '0.6';
                    }, 600);

                    // DO NOT remove completed tasks - they stay on screen!
                } else if (status === 'failed' || status === 'lock_failed') {
                    // For failed tasks move back to queue
                    const targetPos = 10 + Math.random() * 4 - 2;
                    animateHorizontalTask(element, currentLeftPos, targetPos, currentTopPos);
                    task.position = targetPos;
                } else if (oldSection !== section.sectionIndex) {
                    // Transition between sections - move to center of new section
                    const horizontalJitter = Math.random() * 4 - 2;
                    const newPos = section.center + horizontalJitter;

                    animateHorizontalTask(element, currentLeftPos, newPos, currentTopPos);
                    task.position = newPos;
                } else {
                    // Stay in same section but status changed
                    const jitter = Math.random() * 3 - 1.5;
                    const newPos = currentLeftPos + jitter;

                    animateHorizontalTask(element, currentLeftPos, newPos, currentTopPos);
                    task.position = newPos;
                }

                setTimeout(() => {
                    element.classList.remove('task-pulse');
                }, 300);
            }

            // Get text description of status
            function getStatusText(status) {
                const statusMap = {
                    'queued': 'Queued',
                    'processing': 'Processing',
                    'checking_lock': 'Checking Lock',
                    'lock_acquired': 'Lock Acquired',
                    'lock_failed': 'Lock Failed',
                    'processing_progress': 'In Progress',
                    'completed': 'Completed',
                    'failed': 'Failed'
                };
                return statusMap[status] || status;
            }

            // Animation for horizontal movement only
            function animateHorizontalTask(element, fromLeft, toLeft, topPosition) {
                const duration = 600;
                const startTime = performance.now();

                function step(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);

                    const easeProgress = progress < 0.5 ?
                        2 * progress * progress :
                        1 - Math.pow(-2 * progress + 2, 2) / 2;

                    const currentPos = fromLeft + (toLeft - fromLeft) * easeProgress;
                    element.style.left = `${currentPos}%`;
                    element.style.top = `${topPosition}%`;
                    element.style.transform = 'translateY(-50%)';

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    }
                }

                requestAnimationFrame(step);
            }

            // Add log
            function addLog(taskId, status, attempt, message = '') {
                const logPanel = document.getElementById('logPanel');
                const time = new Date().toLocaleTimeString();
                const shortTaskId = taskId ? taskId.substring(0, 6) : 'SYS';

                const logEntry = document.createElement('div');
                logEntry.className = 'log-entry border-b border-gray-800 pb-2 mb-2 text-xs';
                logEntry.innerHTML = `
        <span class="log-time text-blue-400">[${time}]</span>
        <span class="text-yellow-300 ml-1">${shortTaskId}</span>
        <span class="text-green-400 ml-1">${status.toUpperCase()}</span>
        ${message ? `<span class="ml-1">- ${message}</span>` : ''}
    `;

                logPanel.appendChild(logEntry);
                logPanel.scrollTop = logPanel.scrollHeight;

                if (logPanel.children.length > 100) {
                    logPanel.removeChild(logPanel.firstChild);
                }
            }

            // Create tasks
            function createTask(count = 1) {
                if (!csrfToken) {
                    addLog('SYSTEM', 'error', 1, 'CSRF token not found');
                    return;
                }

                addLog('SYSTEM', 'info', 1, `Creating ${count} task(s) with max_concurrent = ${currentMaxConcurrent}`);
                showSuccessPopup(`${count} task(s) created`);

                fetch('/api/tasks/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            count: count,
                            max_concurrent: currentMaxConcurrent
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        // Already showed popup above
                    })
                    .catch(error => {
                        addLog('SYSTEM', 'error', 1, 'Failed to create task');
                        console.error('Error creating task:', error);
                    });
            }

            // Initial message
            setTimeout(() => {
                addLog('SYSTEM', 'info', 1, 'Ready to create tasks');
                addLog('SYSTEM', 'info', 1, `Current max concurrent for new tasks: ${currentMaxConcurrent}`);
            }, 500);
        });
    </script>
</body>

</html>