#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ESP32Servo.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <time.h>

// ============================================================
// Wi-Fi and live CT-ABS API configuration
// ============================================================

const char *WIFI_SSID = "TaraDITO";
const char *WIFI_PASSWORD = "Hudas122005";

const char *GET_COMMANDS_URL =
    "https://ct-abs.com/api/device.php?action=get_cmds";
const char *ACK_COMMAND_URL =
    "https://ct-abs.com/api/device.php?action=ack_cmd";

// Replace this before live upload. It is deliberately not printed by this sketch.
const char *DEVICE_API_KEY = "9a22ced5beb53cabe4d8d5c05e441a88a629a0b72f854ad7";

// ISRG Root X2, used to validate the HTTPS certificate for ct-abs.com.
static const char CTABS_ROOT_CA[] PROGMEM = R"CERT(
-----BEGIN CERTIFICATE-----
MIICGzCCAaGgAwIBAgIQQdKd0XLq7qeAwSxs6S+HUjAKBggqhkjOPQQDAzBPMQsw
CQYDVQQGEwJVUzEpMCcGA1UEChMgSW50ZXJuZXQgU2VjdXJpdHkgUmVzZWFyY2gg
R3JvdXAxFTATBgNVBAMTDElTUkcgUm9vdCBYMjAeFw0yMDA5MDQwMDAwMDBaFw00
MDA5MTcxNjAwMDBaME8xCzAJBgNVBAYTAlVTMSkwJwYDVQQKEyBJbnRlcm5ldCBT
ZWN1cml0eSBSZXNlYXJjaCBHcm91cDEVMBMGA1UEAxMMSVNSRyBSb290IFgyMHYw
EAYHKoZIzj0CAQYFK4EEACIDYgAEzZvVn4CDCuwJSvMWSj5cz3es3mcFDR0HttwW
+1qLFNvicWDEukWVEYmO6gbf9yoWHKS5xcUy4APgHoIYOIvXRdgKam7mAHf7AlF9
ItgKbppbd9/w+kHsOdx1ymgHDB/qo0IwQDAOBgNVHQ8BAf8EBAMCAQYwDwYDVR0T
AQH/BAUwAwEB/zAdBgNVHQ4EFgQUfEKWrt5LSDv6kviejM9ti6lyN5UwCgYIKoZI
zj0EAwMDaAAwZQIwe3lORlCEwkSHRhtFcP9Ymd70/aTSVaYgLXTWNLxBo1BfASdW
tL4ndQavEi51mI38AjEAi/V3bNTIZargCyzuFJ0nN6T5U6VR5CmD1/iQMVtCnwr1
/q4AaOeMSQ+2b1tbFfLn
-----END CERTIFICATE-----
)CERT";

// Enable only after all servo wiring, power, and mechanical clearances
// have been checked.
constexpr bool SERVO_MOVEMENT_ENABLED = true;

// ============================================================
// Exact manual-test servo configuration
// ============================================================

constexpr uint8_t SERVO_COUNT = 5;
constexpr uint8_t SERVO_NUMBERS[SERVO_COUNT] = {1, 2, 3, 4, 5};
constexpr uint8_t SERVO_PINS[SERVO_COUNT] = {38, 4, 40, 6, 15};
constexpr int SERVO_ON_ANGLES[SERVO_COUNT] = {90, 90, 180, 140, 180};
constexpr int SERVO_OFF_ANGLES[SERVO_COUNT] = {0, 0, 0, 80, 0};
constexpr int SERVO_EXTRA_ANGLES[SERVO_COUNT] = {-1, -1, -1, 0, -1};

// Temporary troubleshooting allowlist: Servo 1 is disabled while its wiring
// is being checked; Servos 2, 3, 4, and 5 may move.
constexpr bool SERVO_ENABLED_FOR_TEST[SERVO_COUNT] = {
    false, true, true, true, true};

// ============================================================
// Timing and persistent command tracking
// ============================================================

constexpr unsigned long POLL_INTERVAL_MS = 2000;
constexpr unsigned long ACK_RETRY_INTERVAL_MS = 3000;
constexpr unsigned long HTTP_TIMEOUT_MS = 5000;
constexpr unsigned long SERVO_SETTLE_DELAY_MS = 1000;

Preferences preferences;
Servo servoControllers[SERVO_COUNT];

bool servoAttached = false;
int attachedServoIndex = -1;

uint32_t pendingAcknowledgmentId = 0;
String pendingAcknowledgmentStatus;

unsigned long lastPollAttempt = 0;
unsigned long lastAcknowledgmentAttempt = 0;
bool clockSynchronized = false;

// ============================================================
// Utility
// ============================================================

void setInactiveServoPinsSafe()
{
  for (int index = 0; index < SERVO_COUNT; index++)
  {
    if (!servoAttached || index != attachedServoIndex)
    {
      pinMode(SERVO_PINS[index], OUTPUT);
      digitalWrite(SERVO_PINS[index], LOW);
    }
  }
}

int servoIndexForNumber(int servoNumber)
{
  for (int index = 0; index < SERVO_COUNT; index++)
  {
    if (SERVO_NUMBERS[index] == servoNumber)
    {
      return index;
    }
  }

  return -1;
}

bool isServoEnabledForTest(int servoIndex)
{
  return servoIndex >= 0 &&
         servoIndex < SERVO_COUNT &&
         SERVO_ENABLED_FOR_TEST[servoIndex];
}

bool isConfiguredAngle(int servoIndex, int targetAngle)
{
  if (servoIndex < 0 || servoIndex >= SERVO_COUNT)
  {
    return false;
  }

  return targetAngle == SERVO_ON_ANGLES[servoIndex] ||
         targetAngle == SERVO_OFF_ANGLES[servoIndex] ||
         targetAngle == SERVO_EXTRA_ANGLES[servoIndex];
}

bool isConfiguredCommand(
    int servoIndex,
    const String &command,
    int targetAngle)
{
  if (!isConfiguredAngle(servoIndex, targetAngle))
  {
    return false;
  }

  if (command == "ON")
  {
    return targetAngle == SERVO_ON_ANGLES[servoIndex];
  }

  if (command == "OFF")
  {
    return targetAngle == SERVO_OFF_ANGLES[servoIndex] ||
           targetAngle == SERVO_EXTRA_ANGLES[servoIndex];
  }

  return false;
}

void detachActiveServo()
{
  if (servoAttached && attachedServoIndex >= 0)
  {
    servoControllers[attachedServoIndex].detach();
  }

  servoAttached = false;
  attachedServoIndex = -1;
  setInactiveServoPinsSafe();
}

// ============================================================
// Wi-Fi
// ============================================================

bool ensureWiFiConnected()
{
  if (WiFi.status() == WL_CONNECTED)
  {
    return true;
  }

  Serial.println("Connecting to Wi-Fi...");
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long startedAt = millis();

  while (
      WiFi.status() != WL_CONNECTED &&
      millis() - startedAt < 15000)
  {
    delay(250);
    Serial.print(".");
  }

  Serial.println();

  if (WiFi.status() != WL_CONNECTED)
  {
    Serial.println("Wi-Fi connection failed.");
    return false;
  }

  Serial.print("Wi-Fi connected. IP: ");
  Serial.println(WiFi.localIP());
  return true;
}

bool ensureClockSynchronized()
{
  if (clockSynchronized || time(nullptr) >= 1700000000)
  {
    clockSynchronized = true;
    return true;
  }

  Serial.println("Synchronizing clock for HTTPS certificate checks...");
  configTime(0, 0, "pool.ntp.org", "time.nist.gov");

  unsigned long startedAt = millis();
  while (time(nullptr) < 1700000000 &&
         millis() - startedAt < 10000)
  {
    delay(250);
  }

  clockSynchronized = time(nullptr) >= 1700000000;
  Serial.println(
      clockSynchronized
          ? "Clock synchronized."
          : "Clock synchronization failed.");
  return clockSynchronized;
}

bool isLiveApiKeyConfigured()
{
  return DEVICE_API_KEY[0] != '\0' &&
         strcmp(DEVICE_API_KEY, "PASTE_LIVE_DEVICE_API_KEY_HERE") != 0;
}

// ============================================================
// API acknowledgment
// ============================================================

bool acknowledgePendingCommand()
{
  if (pendingAcknowledgmentId == 0)
  {
    return true;
  }

  if (!ensureWiFiConnected())
  {
    return false;
  }

  if (!ensureClockSynchronized() || !isLiveApiKeyConfigured())
  {
    Serial.println(
        "Acknowledgment blocked: configure the live API key and clock first.");
    return false;
  }

  WiFiClientSecure secureClient;
  secureClient.setCACert(CTABS_ROOT_CA);
  secureClient.setTimeout(HTTP_TIMEOUT_MS);
  HTTPClient http;

  if (!http.begin(secureClient, ACK_COMMAND_URL))
  {
    Serial.println("Could not initialize acknowledgment request.");
    return false;
  }

  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-KEY", DEVICE_API_KEY);

  JsonDocument request;
  request["command_id"] = pendingAcknowledgmentId;
  request["status"] = pendingAcknowledgmentStatus;

  String requestBody;
  serializeJson(request, requestBody);

  int statusCode = http.POST(requestBody);
  String responseBody = http.getString();
  http.end();

  if (statusCode < 200 || statusCode >= 300)
  {
    Serial.print("Acknowledgment failed with HTTP status ");
    Serial.println(statusCode);
    return false;
  }

  JsonDocument response;
  DeserializationError error = deserializeJson(response, responseBody);

  if (error)
  {
    Serial.println("Acknowledgment response was not valid JSON.");
    return false;
  }

  if (!(response["success"] | false))
  {
    Serial.println("API rejected the acknowledgment.");
    return false;
  }

  Serial.print("Command ");
  Serial.print(pendingAcknowledgmentId);
  Serial.print(" acknowledged as ");
  Serial.println(pendingAcknowledgmentStatus);

  // Keep the last successfully commanded servo attached so it continues
  // holding its requested position. A later movement detaches it first.
  pendingAcknowledgmentId = 0;
  pendingAcknowledgmentStatus = "";
  lastAcknowledgmentAttempt = 0;
  return true;
}

// ============================================================
// Physical movement
// ============================================================

bool moveOneServo(int servoIndex, int targetAngle)
{
  if (!SERVO_MOVEMENT_ENABLED)
  {
    Serial.println(
        "API-only mode: movement disabled; no servo was attached.");
    return false;
  }

  if (servoIndex < 0 || servoIndex >= SERVO_COUNT)
  {
    return false;
  }

  if (!isConfiguredAngle(servoIndex, targetAngle))
  {
    Serial.println("Movement rejected: angle is not configured.");
    return false;
  }

  // Only one Servo object is ever attached at a time.
  detachActiveServo();

  int channel = servoControllers[servoIndex].attach(
      SERVO_PINS[servoIndex],
      500,
      2500);

  if (channel < 0)
  {
    Serial.println("Servo attach failed.");
    return false;
  }

  servoAttached = true;
  attachedServoIndex = servoIndex;

  Serial.print("Moving Servo ");
  Serial.print(SERVO_NUMBERS[servoIndex]);
  Serial.print(" on GPIO ");
  Serial.print(SERVO_PINS[servoIndex]);
  Serial.print(" to ");
  Serial.print(targetAngle);
  Serial.println(" degrees.");

  servoControllers[servoIndex].write(targetAngle);
  delay(SERVO_SETTLE_DELAY_MS);

  Serial.println(
      "PWM command and settling delay completed; position is not feedback-confirmed.");
  return true;
}

// ============================================================
// Command retrieval and processing
// ============================================================

bool retrieveAndProcessOldestCommand()
{
  if (!ensureWiFiConnected())
  {
    return false;
  }

  if (!ensureClockSynchronized() || !isLiveApiKeyConfigured())
  {
    Serial.println(
        "Command retrieval blocked: configure the live API key and clock first.");
    return false;
  }

  WiFiClientSecure secureClient;
  secureClient.setCACert(CTABS_ROOT_CA);
  secureClient.setTimeout(HTTP_TIMEOUT_MS);
  HTTPClient http;

  if (!http.begin(secureClient, GET_COMMANDS_URL))
  {
    Serial.println("Could not initialize command request.");
    return false;
  }

  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("X-API-KEY", DEVICE_API_KEY);

  int statusCode = http.GET();
  String responseBody = http.getString();
  http.end();

  if (statusCode < 200 || statusCode >= 300)
  {
    Serial.print("Command retrieval failed with HTTP status ");
    Serial.println(statusCode);
    return false;
  }

  JsonDocument response;
  DeserializationError error = deserializeJson(response, responseBody);

  if (error)
  {
    Serial.println("Command response was not valid JSON.");
    return false;
  }

  if (!(response["success"] | false))
  {
    Serial.println("Command API returned success=false.");
    return false;
  }

  JsonArray commands = response["commands"].as<JsonArray>();

  if (commands.isNull() || commands.size() == 0)
  {
    Serial.println("No pending servo commands.");
    return true;
  }

  // The API orders by command_id. Process only the oldest command.
  JsonObject command = commands[0];
  uint32_t commandId = command["command_id"] | 0;
  int servoNumber = command["servo_number"] | -1;
  String commandName = command["command"] | "";
  int targetAngle = command["target_angle"] | -1;
  int servoIndex = servoIndexForNumber(servoNumber);

  Serial.print("Received command_id ");
  Serial.println(commandId);

  if (!isServoEnabledForTest(servoIndex))
  {
    Serial.println(
        "Temporary test allowlist rejected this servo; no movement performed.");
    pendingAcknowledgmentId = commandId;
    pendingAcknowledgmentStatus = "failed";
    return false;
  }

  if (
      commandId == 0 ||
      !isConfiguredCommand(servoIndex, commandName, targetAngle))
  {
    Serial.println("Command failed validation; no servo movement performed.");
    pendingAcknowledgmentId = commandId;
    pendingAcknowledgmentStatus = "failed";
    return false;
  }

  uint32_t lastExecutedCommand =
      preferences.getUInt("last_executed", 0);

  // A previously completed command may remain pending if its ACK was lost.
  // ACK it without repeating the physical movement.
  if (commandId <= lastExecutedCommand)
  {
    Serial.println(
        "Command was already physically completed; retrying ACK only.");
    pendingAcknowledgmentId = commandId;
    pendingAcknowledgmentStatus = "executed";
    return true;
  }

  if (!SERVO_MOVEMENT_ENABLED)
  {
    Serial.println(
        "API-only mode: command remains pending and will not be acknowledged.");
    return true;
  }

  if (!moveOneServo(servoIndex, targetAngle))
  {
    Serial.println("Movement failed; sending failed acknowledgment.");
    pendingAcknowledgmentId = commandId;
    pendingAcknowledgmentStatus = "failed";
    return false;
  }

  // Persist after the movement and settling delay, before sending the ACK.
  // A reboot after this point will retry ACK without repeating movement.
  preferences.putUInt("last_executed", commandId);
  pendingAcknowledgmentId = commandId;
  pendingAcknowledgmentStatus = "executed";
  return true;
}

// ============================================================
// Arduino lifecycle
// ============================================================

void setup()
{
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("CT-ABS manual servo controller");
  Serial.println("ESP32S3 Dev Module");
  Serial.println("No camera, ultrasonic, AI, or automatic-sorting code.");

  if (SERVO_MOVEMENT_ENABLED)
  {
    Serial.println(
        "WARNING: physical movement is ENABLED. Check wiring and clearance.");
  }
  else
  {
    Serial.println(
        "API-only mode: physical movement is DISABLED.");
  }

  preferences.begin("ctabs-servo", false);
  setInactiveServoPinsSafe();
  ensureWiFiConnected();
  ensureClockSynchronized();
}

void loop()
{
  if (!ensureWiFiConnected())
  {
    delay(1000);
    return;
  }

  // Never retrieve another command while an ACK is pending.
  if (pendingAcknowledgmentId != 0)
  {
    if (
        lastAcknowledgmentAttempt == 0 ||
        millis() - lastAcknowledgmentAttempt >= ACK_RETRY_INTERVAL_MS)
    {
      lastAcknowledgmentAttempt = millis();
      acknowledgePendingCommand();
    }

    delay(50);
    return;
  }

  if (
      lastPollAttempt == 0 ||
      millis() - lastPollAttempt >= POLL_INTERVAL_MS)
  {
    lastPollAttempt = millis();
    retrieveAndProcessOldestCommand();
  }

  delay(20);
}
