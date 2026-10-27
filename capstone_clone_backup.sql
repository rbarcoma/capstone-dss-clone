-- MySQL dump 10.13  Distrib 8.4.13, for Linux (aarch64)
--
-- Host: localhost    Database: capstone_clone_db
-- ------------------------------------------------------
-- Server version	8.4.13

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_trails`
--

DROP TABLE IF EXISTS `audit_trails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_trails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_trails_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_trails_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_trails`
--

LOCK TABLES `audit_trails` WRITE;
/*!40000 ALTER TABLE `audit_trails` DISABLE KEYS */;
INSERT INTO `audit_trails` VALUES (1,1,'Renante Barcoma','Data Management','Upload Dataset','Uploaded combined electricity and climate dataset CSV with 17 raw records aggregated into 17 monthly records.','192.168.65.1','success','2026-10-24 15:49:57','2026-10-24 15:49:57'),(2,1,'Renante Barcoma','Preprocessing','Auto Run Preprocessing','17 processed records generated after dataset upload.','192.168.65.1','success','2026-10-24 15:49:57','2026-10-24 15:49:57'),(3,1,'Renante Barcoma','Preprocessing','Run Preprocessing','17 processed records generated with ML features.','192.168.65.1','success','2026-10-24 15:50:02','2026-10-24 15:50:02'),(4,1,'Renante Barcoma','ML Forecasting','Train Model','Trained the machine learning model using the latest processed dataset.','192.168.65.1','success','2026-10-24 16:48:12','2026-10-24 16:48:12'),(5,1,'Renante Barcoma','Forecasting','Generate Prediction','Generated next-month prediction for 2026-09: 203347.49 kWh.','192.168.65.1','success','2026-10-24 16:48:14','2026-10-24 16:48:14'),(6,1,'Renante Barcoma','Decision Support','Auto Generate DSS Result','Generated DSS result: Moderate Demand and Moderate Readiness.','192.168.65.1','success','2026-10-24 16:48:14','2026-10-24 16:48:14'),(7,1,'Renante Barcoma','Reports','Generate Report','Generated report: Renewable Energy Transition Report - 2026-10-24 16:48.','192.168.65.1','success','2026-10-24 16:48:14','2026-10-24 16:48:14');
/*!40000 ALTER TABLE `audit_trails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('qc-forecasting-dss-cache-6b32bd7812062d51f21828de6ca30195','i:1;',1792979461),('qc-forecasting-dss-cache-6b32bd7812062d51f21828de6ca30195:timer','i:1792979461;',1792979461),('qc-forecasting-dss-cache-f9b74b56d93be95c2682dad730c20b1e','i:1;',1792857013),('qc-forecasting-dss-cache-f9b74b56d93be95c2682dad730c20b1e:timer','i:1792857013;',1792857013);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `climate_records`
--

DROP TABLE IF EXISTS `climate_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `climate_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dataset_id` bigint unsigned DEFAULT NULL,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `temperature` decimal(8,2) DEFAULT NULL,
  `humidity` decimal(8,2) DEFAULT NULL,
  `rainfall` decimal(10,2) DEFAULT NULL,
  `solar_irradiance` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `climate_records_year_month_unique` (`year`,`month`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `climate_records`
--

LOCK TABLES `climate_records` WRITE;
/*!40000 ALTER TABLE `climate_records` DISABLE KEYS */;
INSERT INTO `climate_records` VALUES (1,1,2025,4,27.71,76.37,58.57,6.35,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(2,1,2025,5,27.97,80.08,205.30,5.79,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(3,1,2025,6,27.07,85.39,306.65,4.78,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(4,1,2025,7,26.54,89.37,527.35,3.88,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(5,1,2025,8,26.61,84.87,304.81,4.72,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(6,1,2025,9,26.00,89.34,450.61,3.69,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(7,1,2025,10,26.33,89.42,431.05,3.84,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(8,1,2025,11,26.00,90.86,545.72,3.50,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(9,1,2025,12,24.82,90.02,334.43,3.21,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(10,1,2026,1,23.23,88.02,220.37,3.35,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(11,1,2026,2,23.84,85.34,132.20,4.29,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(12,1,2026,3,24.43,78.07,29.71,5.65,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(13,1,2026,4,27.64,68.44,9.59,6.82,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(14,1,2026,5,29.59,69.05,73.02,6.22,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(15,1,2026,6,28.33,77.29,149.51,5.31,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(16,1,2026,7,27.69,80.21,260.30,5.31,'2026-10-24 15:49:57','2026-10-24 15:49:57'),(17,1,2026,8,26.48,90.90,606.41,3.15,'2026-10-24 15:49:57','2026-10-24 15:49:57');
/*!40000 ALTER TABLE `climate_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consumption_records`
--

DROP TABLE IF EXISTS `consumption_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consumption_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dataset_id` bigint unsigned DEFAULT NULL,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `consumption_kwh` decimal(15,2) NOT NULL,
  `peak_demand_kw` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_complete` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consumption_records_year_month_unique` (`year`,`month`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consumption_records`
--

LOCK TABLES `consumption_records` WRITE;
/*!40000 ALTER TABLE `consumption_records` DISABLE KEYS */;
INSERT INTO `consumption_records` VALUES (1,1,2025,4,226100.00,976.50,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(2,1,2025,5,251300.00,939.40,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(3,1,2025,6,261100.00,952.70,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(4,1,2025,7,223300.00,851.90,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(5,1,2025,8,231700.00,914.90,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(6,1,2025,9,227500.00,793.80,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(7,1,2025,10,233100.00,859.60,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(8,1,2025,11,251300.00,861.00,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(9,1,2025,12,229600.00,820.40,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(10,1,2026,1,201600.00,791.67,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(11,1,2026,2,222540.00,791.67,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(12,1,2026,3,166600.00,838.60,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(13,1,2026,4,157500.00,870.80,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(14,1,2026,5,209300.00,1066.80,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(15,1,2026,6,220500.00,1089.90,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(16,1,2026,7,214200.00,1089.90,'2026-10-24 15:49:57','2026-10-24 15:49:57',1),(17,1,2026,8,179900.00,1057.70,'2026-10-24 15:49:57','2026-10-24 15:49:57',1);
/*!40000 ALTER TABLE `consumption_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `datasets`
--

DROP TABLE IF EXISTS `datasets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `datasets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `validation_errors` json DEFAULT NULL,
  `record_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `datasets_user_id_foreign` (`user_id`),
  CONSTRAINT `datasets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `datasets`
--

LOCK TABLES `datasets` WRITE;
/*!40000 ALTER TABLE `datasets` DISABLE KEYS */;
INSERT INTO `datasets` VALUES (1,1,'combined','HIGHRISE-DATASET.csv','datasets/iiA1Jnf6ecdq2VxIVzGaNQcDNFtpQCw7w7PnxKIJ.csv','valid',NULL,17,'2026-10-24 15:49:57','2026-10-24 15:49:57');
/*!40000 ALTER TABLE `datasets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dss_results`
--

DROP TABLE IF EXISTS `dss_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dss_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `forecast_result_id` bigint unsigned DEFAULT NULL,
  `demand_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `readiness_level` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recommendations` json NOT NULL,
  `priority_actions` json NOT NULL,
  `basis` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dss_results_forecast_result_id_foreign` (`forecast_result_id`),
  KEY `dss_results_user_id_foreign` (`user_id`),
  CONSTRAINT `dss_results_forecast_result_id_foreign` FOREIGN KEY (`forecast_result_id`) REFERENCES `forecast_results` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dss_results_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dss_results`
--

LOCK TABLES `dss_results` WRITE;
/*!40000 ALTER TABLE `dss_results` DISABLE KEYS */;
INSERT INTO `dss_results` VALUES (1,1,1,'Moderate Demand','Moderate Readiness','[\"The forecasted electricity consumption shows a noticeable increase compared to the previous period. This may indicate growing energy demand, population activity, or operational expansion that could require additional renewable energy planning and energy management preparation.\"]','[\"The administration should review facilities or locations with increasing electricity consumption, monitor areas with rapid energy growth, and prioritize renewable energy projects that can help offset future electricity demand.\"]','{\"peak_demand_kw\": 1057.7, \"solar_irradiance\": 3.15, \"average_consumption_kwh\": 218067.058824, \"predicted_consumption_kwh\": 203347.49}','2026-10-24 16:48:14','2026-10-24 16:48:14');
/*!40000 ALTER TABLE `dss_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forecast_results`
--

DROP TABLE IF EXISTS `forecast_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forecast_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `predicted_at` timestamp NULL DEFAULT NULL,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `predicted_consumption_kwh` decimal(15,2) NOT NULL,
  `previous_consumption_kwh` decimal(15,2) DEFAULT NULL,
  `change_percent` decimal(8,2) DEFAULT NULL,
  `mae` decimal(12,4) DEFAULT NULL,
  `rmse` decimal(12,4) DEFAULT NULL,
  `r2_score` decimal(12,4) DEFAULT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Random Forest',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `dataset_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forecast_results_dataset_hash_year_month_unique` (`dataset_hash`,`year`,`month`),
  KEY `forecast_results_user_id_foreign` (`user_id`),
  CONSTRAINT `forecast_results_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forecast_results`
--

LOCK TABLES `forecast_results` WRITE;
/*!40000 ALTER TABLE `forecast_results` DISABLE KEYS */;
INSERT INTO `forecast_results` VALUES (1,1,'2026-10-24 16:48:14',2026,9,203347.49,179900.00,13.03,59365.9026,70684.6888,-7.7672,'Linear Regression','2026-10-24 16:48:14','2026-10-24 16:48:14','24ce258379658acb33a75494da25b307ce06df8a6d4943f3459d33dc1bf7dd9d');
/*!40000 ALTER TABLE `forecast_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forecasts`
--

DROP TABLE IF EXISTS `forecasts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forecasts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL,
  `month` int NOT NULL,
  `predicted_consumption_kwh` decimal(15,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `readiness` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recommendation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forecasts`
--

LOCK TABLES `forecasts` WRITE;
/*!40000 ALTER TABLE `forecasts` DISABLE KEYS */;
/*!40000 ALTER TABLE `forecasts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_08_14_170933_add_two_factor_columns_to_users_table',1),(5,'2026_04_22_145828_add_role_to_users_table',1),(6,'2026_04_22_172832_create_forecasts_table',1),(7,'2026_04_28_000001_create_dss_energy_tables',1),(8,'2026_05_11_123923_create_audit_trails_table',1),(9,'2026_05_16_000001_add_user_id_to_forecast_results_table',1),(10,'2026_05_16_000002_add_predicted_at_and_unique_period_to_forecast_results_table',1),(11,'2026_05_16_000003_add_user_id_to_dss_results_table',1),(12,'2026_05_16_000004_add_permissions_to_users_table',1),(13,'2026_08_23_000001_repair_consumption_records_table',1),(14,'2026_08_23_000002_repair_climate_records_table',1),(15,'2026_10_24_000001_add_dataset_forecast_versioning',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `processed_records`
--

DROP TABLE IF EXISTS `processed_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `processed_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `consumption_kwh` decimal(15,2) NOT NULL,
  `temperature` decimal(8,2) NOT NULL,
  `humidity` decimal(8,2) NOT NULL,
  `rainfall` decimal(10,2) NOT NULL,
  `solar_irradiance` decimal(10,2) NOT NULL,
  `peak_demand_kw` decimal(15,2) NOT NULL,
  `lag_1` decimal(15,2) NOT NULL,
  `lag_2` decimal(15,2) NOT NULL,
  `trend` int unsigned NOT NULL,
  `month_sin` decimal(12,8) NOT NULL,
  `month_cos` decimal(12,8) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `processed_records_year_month_unique` (`year`,`month`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `processed_records`
--

LOCK TABLES `processed_records` WRITE;
/*!40000 ALTER TABLE `processed_records` DISABLE KEYS */;
INSERT INTO `processed_records` VALUES (1,2025,4,226100.00,27.71,76.37,58.57,6.35,976.50,226100.00,226100.00,1,0.86602540,-0.50000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(2,2025,5,251300.00,27.97,80.08,205.30,5.79,939.40,226100.00,251300.00,2,0.50000000,-0.86602540,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(3,2025,6,261100.00,27.07,85.39,306.65,4.78,952.70,251300.00,226100.00,3,0.00000000,-1.00000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(4,2025,7,223300.00,26.54,89.37,527.35,3.88,851.90,261100.00,251300.00,4,-0.50000000,-0.86602540,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(5,2025,8,231700.00,26.61,84.87,304.81,4.72,914.90,223300.00,261100.00,5,-0.86602540,-0.50000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(6,2025,9,227500.00,26.00,89.34,450.61,3.69,793.80,231700.00,223300.00,6,-1.00000000,0.00000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(7,2025,10,233100.00,26.33,89.42,431.05,3.84,859.60,227500.00,231700.00,7,-0.86602540,0.50000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(8,2025,11,251300.00,26.00,90.86,545.72,3.50,861.00,233100.00,227500.00,8,-0.50000000,0.86602540,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(9,2025,12,229600.00,24.82,90.02,334.43,3.21,820.40,251300.00,233100.00,9,0.00000000,1.00000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(10,2026,1,201600.00,23.23,88.02,220.37,3.35,791.67,229600.00,251300.00,10,0.50000000,0.86602540,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(11,2026,2,222540.00,23.84,85.34,132.20,4.29,791.67,201600.00,229600.00,11,0.86602540,0.50000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(12,2026,3,166600.00,24.43,78.07,29.71,5.65,838.60,222540.00,201600.00,12,1.00000000,0.00000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(13,2026,4,157500.00,27.64,68.44,9.59,6.82,870.80,166600.00,222540.00,13,0.86602540,-0.50000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(14,2026,5,209300.00,29.59,69.05,73.02,6.22,1066.80,157500.00,166600.00,14,0.50000000,-0.86602540,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(15,2026,6,220500.00,28.33,77.29,149.51,5.31,1089.90,209300.00,157500.00,15,0.00000000,-1.00000000,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(16,2026,7,214200.00,27.69,80.21,260.30,5.31,1089.90,220500.00,209300.00,16,-0.50000000,-0.86602540,'2026-10-24 15:50:02','2026-10-24 15:50:02'),(17,2026,8,179900.00,26.48,90.90,606.41,3.15,1057.70,214200.00,220500.00,17,-0.86602540,-0.50000000,'2026-10-24 15:50:02','2026-10-24 15:50:02');
/*!40000 ALTER TABLE `processed_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `summary` json NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reports_user_id_foreign` (`user_id`),
  CONSTRAINT `reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
INSERT INTO `reports` VALUES (1,1,'Renewable Energy Transition Report - 2026-10-24 16:48','analytics_forecast_dss','{\"source\": \"manual\", \"statuses\": {\"dss_status\": \"Generated\", \"model_used\": \"Linear Regression\", \"data_status\": \"Preprocessed\", \"forecast_status\": \"Completed\"}, \"analytics\": {\"record_count\": 17, \"latest_period\": \"2026-08\", \"average_peak_demand_kw\": 915.72, \"latest_consumption_kwh\": 179900, \"average_consumption_kwh\": 218067.06, \"highest_consumption_kwh\": 261100, \"average_solar_irradiance\": 4.7}, \"latest_dss\": {\"id\": 1, \"basis\": {\"peak_demand_kw\": 1057.7, \"solar_irradiance\": 3.15, \"average_consumption_kwh\": 218067.058824, \"predicted_consumption_kwh\": 203347.49}, \"user_id\": 1, \"created_at\": \"2026-10-24T16:48:14.000000Z\", \"updated_at\": \"2026-10-24T16:48:14.000000Z\", \"demand_status\": \"Moderate Demand\", \"readiness_level\": \"Moderate Readiness\", \"recommendations\": [\"The forecasted electricity consumption shows a noticeable increase compared to the previous period. This may indicate growing energy demand, population activity, or operational expansion that could require additional renewable energy planning and energy management preparation.\"], \"priority_actions\": [\"The administration should review facilities or locations with increasing electricity consumption, monitor areas with rapid energy growth, and prioritize renewable energy projects that can help offset future electricity demand.\"], \"forecast_result_id\": 1}, \"report_basis\": \"This report is based on uploaded electricity and climate records, monthly aggregated preprocessing results, machine learning forecasting output, and decision support classification.\", \"dss_reasoning\": {\"demand_reason\": \"The predicted consumption is 93.25% of the historical average consumption, which supports the Moderate Demand classification.\", \"readiness_reason\": \"Readiness is based on solar irradiance, peak demand, and predicted consumption. The current DSS result classified readiness as Moderate Readiness.\"}, \"latest_forecast\": {\"id\": 1, \"mae\": 59365.9026, \"rmse\": 70684.6888, \"year\": 2026, \"month\": 9, \"user_id\": 1, \"r2_score\": -7.7672, \"created_at\": \"2026-10-24T16:48:14.000000Z\", \"model_type\": \"Linear Regression\", \"updated_at\": \"2026-10-24T16:48:14.000000Z\", \"dataset_hash\": \"24ce258379658acb33a75494da25b307ce06df8a6d4943f3459d33dc1bf7dd9d\", \"predicted_at\": \"2026-10-24T16:48:14.000000Z\", \"change_percent\": 13.03, \"previous_consumption_kwh\": 179900, \"predicted_consumption_kwh\": 203347.49}, \"forecast_accuracy\": {\"mae\": 59365.9026, \"rmse\": 70684.6888, \"r2_score\": -7.7672, \"interpretation\": \"The model has limited explanatory strength. More data or improved features may be needed.\"}, \"dataset_processing\": {\"uploaded_at\": \"2026-10-24 15:49:57\", \"uploaded_by\": \"Renante Barcoma\", \"uploaded_dataset\": \"HIGHRISE-DATASET.csv\", \"processed_records\": 17, \"raw_daily_records\": 17, \"preprocessing_status\": \"Completed\", \"climate_records_matched\": 17, \"latest_processed_period\": \"2026-08\", \"monthly_records_generated\": 17}, \"prediction_explanation\": {\"model\": \"Linear Regression\", \"features\": [\"year\", \"month\", \"temperature\", \"humidity\", \"rainfall\", \"solar_irradiance\", \"peak_demand_kw\", \"lag_1\", \"lag_2\", \"trend\", \"month_sin\", \"month_cos\"], \"description\": \"The system uses Linear Regression. Daily uploaded records are aggregated into monthly records, preprocessing creates lag, trend, and seasonal features, and the trained coefficients are applied only to the month immediately after the latest consecutive complete, valid actual month in the dataset, independently of the current date. The sum of the intercept and weighted feature values becomes the predicted monthly consumption.\", \"dataset_hash\": \"24ce258379658acb33a75494da25b307ce06df8a6d4943f3459d33dc1bf7dd9d\", \"forecast_period\": \"2026-09\"}}',NULL,'2026-10-24 16:48:14','2026-10-24 16:48:14');
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('8aUzRTjgwC0FzbNPffy4KZ5pHngxRrWbhg5oZlc6',1,'192.168.65.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJaNnVNelJ2czdhcFZydkRRM0F2Q3V5TUhyU0duRUJuMHFGbEc1dVBNIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDgyXC9hZG1pblwvZm9yZWNhc3RpbmciLCJyb3V0ZSI6ImFkbWluLmZvcmVjYXN0aW5nIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9',1792862668),('xb1rTD8JaFMaO1sX3Mk0CmErjqWM8c8Egb6IWURW',1,'192.168.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJJb0xLTFloTEdkelIyVVUyWUMxOTNPOXN5R2pWTkVqblc3aHBuVnIyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDgyIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9',1792979409);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `permissions` json DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Renante Barcoma','renantebarcoma1@gmail.com',NULL,'$2y$12$wIUZ8KFhgW7qOKHWaVHSQOmvTQCUk7kdtKE/5hAYf0VgxPvQpBZxu',NULL,NULL,NULL,'admin',NULL,NULL,'2026-10-24 08:51:17','2026-10-24 08:51:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-26  1:53:43
