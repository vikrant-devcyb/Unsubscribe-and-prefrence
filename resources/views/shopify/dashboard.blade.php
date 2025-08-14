<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Unsubscribe Customer - Dashboard</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: #f5f7fa;
      color: #333;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      padding: 20px;
    }

    .dashboard {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
      padding: 40px;
      max-width: 480px;
      width: 100%;
      text-align: center;
      animation: fadeIn 0.4s ease-in-out;
    }

    .dashboard h1 {
      font-size: 24px;
      color: #2c7be5;
      margin-bottom: 8px;
    }

    .shop-name {
      font-size: 14px;
      color: #666;
      margin-bottom: 30px;
    }

    .stat {
      font-size: 36px;
      font-weight: 600;
      color: #2c7be5;
      margin-bottom: 6px;
    }

    .label {
      font-size: 14px;
      color: #555;
      margin-bottom: 24px;
    }

    .note {
      font-size: 13px;
      color: #888;
      margin-top: 20px;
    }

    .footer {
      font-size: 12px;
      color: #aaa;
      margin-top: 30px;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <div class="dashboard">
    <h1>Unsubscribe Customer</h1>
    <div class="shop-name">Store: <strong>{{ $shop }}</strong></div>

    <div class="stat">{{ $unsubscribedCount ?? 12345 }}</div>

    @if(!empty($lastUnsubscribedAt))
      <div class="label">Last Unsubscribed: {{ $lastUnsubscribedAt }}</div>
    @endif


    <div class="note">
      Helping you stay compliant by unsubscribing customers from email marketing.
    </div>

    <div class="footer">
      &copy; {{ date('Y') }} Handle Unsubscribe and Preference
    </div>
  </div>

</body>
</html>
