<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring - Serveurs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2rem;
        }
        
        .servers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .server-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .server-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        
        .server-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 1rem;
        }
        
        .server-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-online {
            background: #d4edda;
            color: #155724;
        }
        
        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }
        
        .server-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #333;
            margin-top: 0.3rem;
            font-weight: 600;
        }
        
        .cpu-bar, .ram-bar, .disk-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.3rem;
        }
        
        .progress {
            height: 100%;
            background: #667eea;
            transition: width 0.5s ease;
        }
        
        .progress.high {
            background: #dc3545;
        }
        
        .progress.medium {
            background: #ffc107;
        }
        
        .last-update {
            font-size: 0.8rem;
            color: #999;
            margin-top: 1rem;
            text-align: right;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .no-servers {
            text-align: center;
            color: white;
            padding: 3rem;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🖥️ Monitoring - Serveurs</h1>
        
        @if($servers->isEmpty())
            <div class="no-servers">
                <p>Aucun serveur enregistré</p>
            </div>
        @else
            <div class="servers-grid">
                @foreach($servers as $server)
                    <div class="server-card">
                        <div class="server-header">
                            <div class="server-name">{{ $server->name }}</div>
                            <span class="status-badge {{ $server->status === 'online' ? 'status-online' : 'status-offline' }}">
                                {{ $server->status }}
                            </span>
                        </div>
                        
                        <div class="server-info">
                            <div class="info-item">
                                <div class="info-label">IP Address</div>
                                <div class="info-value">{{ $server->ip_address }}</div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Last Check</div>
                                <div class="info-value">
                                    @if($server->last_check)
                                        {{ $server->last_check->diffForHumans() }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">CPU Usage</div>
                            <div class="info-value">{{ number_format($server->cpu_usage ?? 0, 1) }}%</div>
                            <div class="cpu-bar">
                                <div class="progress {{ ($server->cpu_usage ?? 0) > 80 ? 'high' : (($server->cpu_usage ?? 0) > 50 ? 'medium' : '') }}" style="width: {{ $server->cpu_usage ?? 0 }}%"></div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">RAM Usage</div>
                            <div class="info-value">{{ number_format($server->ram_usage ?? 0, 1) }}%</div>
                            <div class="ram-bar">
                                <div class="progress {{ ($server->ram_usage ?? 0) > 80 ? 'high' : (($server->ram_usage ?? 0) > 50 ? 'medium' : '') }}" style="width: {{ $server->ram_usage ?? 0 }}%"></div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Disk Usage</div>
                            <div class="info-value">{{ number_format($server->disk_usage ?? 0, 1) }}%</div>
                            <div class="disk-bar">
                                <div class="progress {{ ($server->disk_usage ?? 0) > 80 ? 'high' : (($server->disk_usage ?? 0) > 50 ? 'medium' : '') }}" style="width: {{ $server->disk_usage ?? 0 }}%"></div>
                            </div>
                        </div>
                        
                        <div class="last-update">
                            Updated: {{ $server->last_check ? $server->last_check->format('Y-m-d H:i:s') : 'Never' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    
    <script>
        // Auto-refresh every 5 seconds
        setInterval(() => {
            location.reload();
        }, 5000);
    </script>
</body>
</html>