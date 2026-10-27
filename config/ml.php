<?php

return [
    // Sail supplies /opt/venv/bin/python through the image environment.
    'python' => env('ML_PYTHON', file_exists(base_path('venv314/Scripts/python.exe'))
        ? base_path('venv314/Scripts/python.exe')
        : 'python3'),
];
