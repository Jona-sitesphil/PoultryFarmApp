import admin from 'firebase-admin';
import mysql from 'mysql2/promise';

const firebaseApp = admin.initializeApp({
  credential: admin.credential.applicationDefault(),
  databaseURL:
    process.env.FIREBASE_DATABASE_URL ??
    'https://thesispoultry-ccb04-default-rtdb.firebaseio.com',
});
const database = firebaseApp.database();

const sql = await mysql.createConnection({
  host: process.env.MYSQL_HOST ?? '127.0.0.1',
  port: Number(process.env.MYSQL_PORT ?? 3306),
  user: process.env.MYSQL_USER ?? 'root',
  password: process.env.MYSQL_PASSWORD ?? '',
  database: process.env.MYSQL_DATABASE ?? 'thesis_poultry_system_db',
});

const asNumber = (value) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
};

const asBoolean = (value, fallback = false) => {
  if (value == null) return fallback;
  if (typeof value === 'boolean') return value;
  return !['0', 'false', 'off', 'no', ''].includes(String(value).toLowerCase());
};

const asIsoDate = (value) => {
  const date = value ? new Date(value) : new Date();
  return Number.isNaN(date.getTime()) ? new Date().toISOString() : date.toISOString();
};

async function rows(table) {
  const [result] = await sql.query(`SELECT * FROM \`${table}\``);
  return result;
}

async function writePath(path, documents) {
  const updates = Object.fromEntries(
    documents.map((document) => [document.id, document.data]),
  );
  if (Object.keys(updates).length > 0) {
    await database.ref(path).update(updates);
  }
  console.log(`${path}: ${documents.length} records written`);
}

const sensorDocuments = (await rows('sensor_readings')).map((row) => ({
  id: `legacy-${row.id}`,
  data: {
    temperature: asNumber(row.temperature),
    humidity: asNumber(row.humidity),
    heat_index: row.heat_index == null ? null : asNumber(row.heat_index),
    status: row.status ?? 'NORMAL',
    fan_status: row.fan_status ?? 'OFF',
    bulb_status: row.bulb_status ?? 'OFF',
    reading_time: asIsoDate(row.reading_time),
  },
}));

const alertDocuments = (await rows('alerts')).map((row) => ({
  id: `legacy-${row.id}`,
  data: {
    alert_type: row.alert_type ?? 'info',
    title: row.title ?? 'System alert',
    description: row.description ?? '',
    location: row.location ?? 'House 1 - Zone A',
    sensor_name: row.sensor_name ?? 'Environmental Sensor',
    icon: row.icon ?? 'warning',
    created_at: asIsoDate(row.created_at),
    is_read: asBoolean(row.is_read, false),
  },
}));

const reportDocuments = (await rows('report_history')).map((row) => ({
  id: `legacy-${row.id}`,
  data: {
    report_name: row.report_name ?? 'Sensor report',
    date_range: row.date_range ?? 'Last 7 Days',
    generated_on: asIsoDate(row.generated_on),
    generated_by: row.generated_by ?? 'system',
  },
}));

const [settings] = await rows('settings');
const systemSettingRows = await rows('system_settings');
const systemSettings = Object.fromEntries(
  systemSettingRows.map((row) => [row.setting_key, row.setting_value]),
);
const existingControlSnapshot = await database
  .ref('system_settings/control')
  .once('value');
const existingControl = existingControlSnapshot.val() ?? {};

await writePath('sensor_readings', sensorDocuments);
await writePath('alerts', alertDocuments);
await writePath('report_history', reportDocuments);

await database.ref('settings/general').update({
  farm_name: settings?.farm_name ?? 'Bolbok Poultry Farm - House 1',
  description: settings?.description ?? '',
  timezone: settings?.timezone ?? '(GMT+08:00) Asia/Manila',
  date_format: settings?.date_format ?? 'MM/DD/YYYY',
  language: settings?.language ?? 'English',
  auto_refresh: asBoolean(settings?.auto_refresh, true),
  data_logging: asBoolean(settings?.data_logging, true),
  unit_system: settings?.unit_system ?? 'Metric (°C, %, ppm)',
  theme: settings?.theme ?? 'Light',
  dashboard_view: settings?.dashboard_view ?? 'Overview',
  confirm_action: asBoolean(settings?.confirm_action, true),
  temp_unit: settings?.temp_unit ?? '°C',
  humidity_unit: settings?.humidity_unit ?? '%',
  ammonia_unit: settings?.ammonia_unit ?? 'ppm',
  decimal_places: asNumber(settings?.decimal_places ?? 1),
  chart_data_points: settings?.chart_data_points ?? '24 Hours',
});

await database.ref('system_settings/control').update({
  system_enabled:
    existingControl.system_enabled ??
    asBoolean(systemSettings.system_enabled, false),
  system_mode: systemSettings.system_mode ?? 'automatic',
  max_temp: asNumber(systemSettings.max_temp ?? 29),
  max_hum: asNumber(systemSettings.max_hum ?? 100),
  min_temp: asNumber(systemSettings.min_temp ?? 27.5),
  exhaust_fan: String(systemSettings.exhaust_fan ?? 'OFF').toUpperCase() === 'ON',
  heater: String(systemSettings.heater ?? 'OFF').toUpperCase() === 'ON',
  cooling_mist: String(systemSettings.cooling_mist ?? 'OFF').toUpperCase() === 'ON',
  email_notifications: asBoolean(systemSettings.email_notifications, true),
});

await sql.end();
console.log('Migration complete. No user or password data was migrated.');
