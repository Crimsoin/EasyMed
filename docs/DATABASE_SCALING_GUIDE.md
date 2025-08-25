# EasyMed Database Scaling Guide

## Current Database Status

**Current Size:** 68 KB (0.07 MB)  
**Total Records:** 21  
**Database Type:** SQLite  
**Average Record Size:** 3.3 KB per record  

## Database Growth Projections

| Records | Estimated Size |
|---------|---------------|
| 1,000   | ~3.2 MB       |
| 5,000   | ~15.8 MB      |
| 10,000  | ~31.6 MB      |
| 50,000  | ~158 MB       |
| 100,000 | ~316 MB       |

## SQLite Limitations and When to Upgrade

### SQLite is suitable for:
- **Small to medium clinics:** Up to 50-100 concurrent users
- **Database size:** Up to 281 TB (theoretical), practically up to 1-2 GB
- **Single server deployments**
- **Read-heavy workloads**

### Consider upgrading when:
- **Concurrent users exceed 50-100**
- **Database size approaches 1 GB**
- **High write concurrency needed**
- **Multiple server deployment required**

## Migration Strategies for Production

### Option 1: Stay with SQLite (Recommended for small clinics)

#### Optimization Steps:
1. **Enable WAL mode** for better concurrency:
   ```sql
   PRAGMA journal_mode = WAL;
   ```

2. **Optimize SQLite settings:**
   ```sql
   PRAGMA cache_size = 10000;
   PRAGMA temp_store = memory;
   PRAGMA mmap_size = 268435456;
   ```

3. **Regular maintenance:**
   ```sql
   PRAGMA optimize;
   VACUUM;
   ```

#### Implementation:
```php
// Add to includes/database.php
public function optimizeForProduction() {
    $this->pdo->exec("PRAGMA journal_mode = WAL");
    $this->pdo->exec("PRAGMA cache_size = 10000");
    $this->pdo->exec("PRAGMA temp_store = memory");
    $this->pdo->exec("PRAGMA mmap_size = 268435456");
    $this->pdo->exec("PRAGMA optimize");
}
```

### Option 2: Migrate to MySQL/PostgreSQL

#### Migration Benefits:
- **Better concurrency:** 1000+ concurrent users
- **Advanced features:** Stored procedures, triggers, views
- **Replication support:** Master-slave setups
- **Better performance:** For large datasets

#### Migration Steps:

1. **Update config.php:**
```php
define('DB_TYPE', 'mysql');
define('DB_HOST', 'your-mysql-host');
define('DB_USERNAME', 'your-username');
define('DB_PASSWORD', 'your-password');
define('DB_NAME', 'easymed_production');
```

2. **Create MySQL migration script:**
```php
// migrate_to_mysql.php
require_once 'includes/config.php';

// Export SQLite data
$sqlite = new PDO('sqlite:' . SQLITE_PATH);
$mysql = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);

// Create tables in MySQL (convert SQLite schema)
// Transfer data table by table
```

### Option 3: Cloud Database Solutions

#### AWS RDS (Recommended for scale)
- **MySQL/PostgreSQL** on managed infrastructure
- **Automatic backups** and point-in-time recovery
- **Auto-scaling** capabilities
- **Multi-AZ deployments** for high availability

#### Configuration:
```php
// Cloud database config
define('DB_HOST', 'your-rds-endpoint.amazonaws.com');
define('DB_PORT', 3306);
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'secure-password');
define('DB_NAME', 'easymed');

// Connection pooling
define('DB_MAX_CONNECTIONS', 100);
define('DB_TIMEOUT', 30);
```

## Performance Optimization Strategies

### 1. Database Indexing
```sql
-- Add indexes for frequently queried fields
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_doctor ON appointments(doctor_id);
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_activity_logs_user_date ON activity_logs(user_id, created_at);
```

### 2. Query Optimization
```php
// Use prepared statements with proper indexing
$stmt = $db->prepare("
    SELECT a.*, u.name as patient_name, d.name as doctor_name 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    JOIN doctors d ON a.doctor_id = d.id 
    WHERE a.appointment_date BETWEEN ? AND ? 
    ORDER BY a.appointment_date DESC
");
```

### 3. Caching Strategy
```php
// Implement Redis/Memcached for session storage
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://127.0.0.1:6379');

// Cache frequent queries
class CacheManager {
    private $redis;
    
    public function get($key) {
        return $this->redis->get($key);
    }
    
    public function set($key, $value, $ttl = 3600) {
        return $this->redis->setex($key, $ttl, serialize($value));
    }
}
```

## Deployment Recommendations

### Small Clinic (< 1000 patients)
- **Database:** Optimized SQLite
- **Server:** Shared hosting or VPS
- **Backup:** Daily file backups

### Medium Clinic (1000-10000 patients)
- **Database:** MySQL on VPS/Cloud
- **Server:** Dedicated VPS (2-4 CPU cores, 4-8GB RAM)
- **Backup:** Automated database backups
- **Monitoring:** Basic performance monitoring

### Large Clinic/Multi-location (10000+ patients)
- **Database:** MySQL/PostgreSQL on cloud (AWS RDS, Google Cloud SQL)
- **Server:** Load-balanced application servers
- **CDN:** Static asset delivery
- **Backup:** Multi-region backups with point-in-time recovery
- **Monitoring:** Comprehensive APM (Application Performance Monitoring)

## Cost Considerations

### SQLite (Current)
- **Cost:** $0 (included with hosting)
- **Maintenance:** Minimal

### MySQL on VPS
- **Cost:** $20-100/month
- **Maintenance:** Moderate

### Cloud Database (AWS RDS)
- **Cost:** $50-500/month (based on usage)
- **Maintenance:** Minimal (managed service)

## Migration Timeline

1. **Immediate (Current setup):**
   - Optimize SQLite settings
   - Add proper indexing
   - Implement basic caching

2. **6 months (Growth phase):**
   - Monitor performance metrics
   - Plan MySQL migration if needed

3. **12 months (Scale phase):**
   - Consider cloud migration
   - Implement advanced monitoring

## Monitoring and Alerts

```php
// Performance monitoring
class DatabaseMonitor {
    public function checkPerformance() {
        $metrics = [
            'db_size' => $this->getDatabaseSize(),
            'query_time' => $this->getAverageQueryTime(),
            'connection_count' => $this->getConnectionCount(),
            'slow_queries' => $this->getSlowQueries()
        ];
        
        // Alert if thresholds exceeded
        if ($metrics['db_size'] > 1000000000) { // 1GB
            $this->sendAlert('Database size approaching limit');
        }
        
        return $metrics;
    }
}
```

## Backup Strategy

### Current (SQLite)
```bash
# Daily backup script
cp database/easymed.sqlite backups/easymed_$(date +%Y%m%d).sqlite
```

### Production (MySQL)
```bash
# Automated backup
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME > backup_$(date +%Y%m%d_%H%M%S).sql
```

## Conclusion

Your current SQLite setup is perfectly adequate for a small to medium clinic. Consider upgrading when you reach:
- **1000+ active patients**
- **50+ concurrent users**
- **Database size > 500 MB**
- **Performance issues**

The migration path should be: SQLite → MySQL on VPS → Cloud Database (AWS RDS/Google Cloud SQL) as your clinic grows.
