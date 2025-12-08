<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Under Maintenance - EasyMed</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-cyan: #00bcd4;
            --dark-cyan: #0097a7;
            --light-cyan: #e0f7fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #00bcd4 0%, #0097a7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .maintenance-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 3rem 2rem;
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-cyan);
            margin-bottom: 2rem;
        }

        .logo i {
            font-size: 2.5rem;
        }

        .icon-container {
            margin-bottom: 2rem;
        }

        .maintenance-icon {
            font-size: 5rem;
            color: var(--primary-cyan);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .info-box {
            background: var(--light-cyan);
            border-left: 4px solid var(--primary-cyan);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }

        .info-box h3 {
            color: var(--dark-cyan);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .info-box p {
            color: #555;
            font-size: 0.95rem;
            margin: 0;
        }

        .contact-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #eee;
        }

        .contact-info h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .contact-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-cyan);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .contact-btn:hover {
            background: var(--dark-cyan);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 188, 212, 0.3);
        }

        .footer-text {
            margin-top: 2rem;
            color: #999;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .maintenance-container {
                padding: 2rem 1.5rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .maintenance-icon {
                font-size: 4rem;
            }

            .contact-links {
                flex-direction: column;
            }

            .contact-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="logo">
            <i class="fas fa-stethoscope"></i>
            <span>EasyMed</span>
        </div>

        <div class="icon-container">
            <i class="fas fa-tools maintenance-icon"></i>
        </div>

        <h1>We'll Be Back Soon!</h1>
        
        <p>
            Our website is currently undergoing scheduled maintenance to improve your experience. 
            We apologize for any inconvenience this may cause.
        </p>

        <p class="footer-text">
            Thank you for your patience and understanding.
        </p>
    </div>
</body>
</html>
