<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloodSync - Blood Donation Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    
    <!-- Custom CSS -->
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }
        
        /* Hide password toggle icon in print */
        @media print {
            .password-toggle { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-50">