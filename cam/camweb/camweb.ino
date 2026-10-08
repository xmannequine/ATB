


#include <Arduino.h>
#include "esp_camera.h"
#include <WiFi.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <TJpg_Decoder.h>
#include <math.h>
#include <time.h>
#include "board_config.h"

// =====================================================
// Wi-Fi
// =====================================================

const char *ssid = "TaraDITO";
const char *password = "Hudas122005";

// =====================================================
// LOCAL AI SERVER
// =====================================================

const char *AI_SERVER = "192.168.8.68";
const uint16_t AI_PORT = 5000;
const char *AI_PATH = "/predict";

// =====================================================
// Live monitor snapshot upload
// =====================================================

const char *CAMERA_UPLOAD_HOST = "ct-abs.com";
const char *CAMERA_UPLOAD_PATH = "/api/camera.php";
const char *CAMERA_API_KEY = "w6YeD8vHKt48X8X8LMXE9xGiTtTchsZfmWgIczCXywA=";
const unsigned long CAMERA_UPLOAD_INTERVAL = 30000;

static const char CAMERA_ROOT_CA[] PROGMEM = R"CERT(
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

// =====================================================
// Camera Server
// =====================================================
 
void startCameraServer();
void setupLedFlash();

// =====================================================
// Detection Settings
// =====================================================

const unsigned long DETECTION_INTERVAL = 200;

const int GRID_WIDTH = 8;
const int GRID_HEIGHT = 6;
const int GRID_CELLS = GRID_WIDTH * GRID_HEIGHT;

const float CHANGE_THRESHOLD = 2.0;
const float REMOVAL_THRESHOLD = 8.0;

const int DETECTION_CONFIRMATIONS = 2;
const int REMOVAL_CONFIRMATIONS = 3;

const int CAMERA_CAPTURE_RETRIES = 3;
const int CAMERA_CAPTURE_RETRY_DELAY = 20;

const unsigned long AI_CONNECT_TIMEOUT = 3000;
const unsigned long AI_RESPONSE_TIMEOUT = 10000;

const unsigned long WIFI_CHECK_INTERVAL = 5000;

// =====================================================
// Detection State
// =====================================================

enum DetectionState
{
  WAITING_FOR_OBJECT,
  WAITING_FOR_REMOVAL
};

DetectionState detectionState = WAITING_FOR_OBJECT;

// =====================================================
// Timing
// =====================================================

unsigned long lastDetectionCheck = 0;
unsigned long lastWiFiCheck = 0;
unsigned long lastCameraUpload = 0;

// =====================================================
// Fingerprint
// =====================================================

float backgroundFingerprint[GRID_CELLS];
float currentFingerprint[GRID_CELLS];

bool backgroundReady = false;

uint32_t gridBrightnessSum[GRID_CELLS];
uint32_t gridPixelCount[GRID_CELLS];

bool fingerprintDecodeActive = false;

// =====================================================
// Confirmation Counters
// =====================================================

int detectionConfirmCount = 0;
int removalConfirmCount = 0;

// =====================================================
// Serial Diagnostics
// =====================================================

bool decoderDebugPrinted = false;
bool fingerprintInfoPrinted = false;

// =====================================================
// Function Prototypes
// =====================================================

camera_fb_t *captureFrameWithRetry();

void clearFingerprintBuffers();

bool jpegFingerprintCallback(
  int16_t x,
  int16_t y,
  uint16_t w,
  uint16_t h,
  uint16_t *bitmap
);

bool createFingerprint(
  camera_fb_t *fb,
  float *fingerprint
);

void captureBackgroundReference(
  camera_fb_t *fb
);

float calculateChange(
  camera_fb_t *fb
);

bool isObjectPresent(
  camera_fb_t *fb
);

bool isObjectRemoved(
  camera_fb_t *fb
);

bool detectObjectChange(
  camera_fb_t *fb
);

bool sendImageToAI(
  camera_fb_t *fb,
  String *categoryOut,
  float *confidenceOut
);

bool uploadCameraEventToLiveMonitor(
  const char *eventName,
  const char *category,
  float confidence
);

bool uploadSnapshotToLiveMonitor(
  camera_fb_t *fb
);

void ensureWiFiConnected();

void printSeparator();

void resetDetectionState();

// =====================================================
// Utility
// =====================================================

void printSeparator()
{
  Serial.println();
  Serial.println("=================================");
}

// =====================================================
// Wi-Fi Recovery
// =====================================================

void ensureWiFiConnected()
{
  if (WiFi.status() == WL_CONNECTED)
  {
    return;
  }

  Serial.println();
  Serial.println("WARNING: Wi-Fi disconnected.");
  Serial.println("Attempting Wi-Fi reconnect...");

  WiFi.disconnect();
  WiFi.begin(ssid, password);

  unsigned long startTime = millis();

  while (
    WiFi.status() != WL_CONNECTED &&
    millis() - startTime < 10000
  )
  {
    delay(250);
    Serial.print(".");
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED)
  {
    Serial.println("Wi-Fi reconnected.");

    Serial.print("IP address: ");
    Serial.println(WiFi.localIP());

    Serial.print("Gateway: ");
    Serial.println(WiFi.gatewayIP());
  }
  else
  {
    Serial.println("Wi-Fi reconnect failed.");
  }
}

// =====================================================
// Reset Detection State
// =====================================================

void resetDetectionState()
{
  detectionState = WAITING_FOR_OBJECT;

  detectionConfirmCount = 0;
  removalConfirmCount = 0;
}

// =====================================================
// Capture Camera Frame
// =====================================================

camera_fb_t *captureFrameWithRetry()
{
  camera_fb_t *fb = nullptr;

  for (
    int attempt = 1;
    attempt <= CAMERA_CAPTURE_RETRIES;
    attempt++
  )
  {
    fb = esp_camera_fb_get();

    if (fb != nullptr)
    {
      return fb;
    }

    Serial.print("Camera capture attempt ");
    Serial.print(attempt);
    Serial.println(" failed.");

    delay(CAMERA_CAPTURE_RETRY_DELAY);
  }

  Serial.println("ERROR: Camera capture failed.");

  return nullptr;
}

// =====================================================
// Clear Fingerprint Buffers
// =====================================================

void clearFingerprintBuffers()
{
  for (int i = 0; i < GRID_CELLS; i++)
  {
    gridBrightnessSum[i] = 0;
    gridPixelCount[i] = 0;
  }
}

// =====================================================
// JPEG Decoder Callback
// =====================================================

bool jpegFingerprintCallback(
  int16_t x,
  int16_t y,
  uint16_t w,
  uint16_t h,
  uint16_t *bitmap
)
{
  if (!fingerprintDecodeActive)
  {
    return true;
  }

  if (bitmap == nullptr)
  {
    return false;
  }

  if (!decoderDebugPrinted)
  {
    Serial.println();
    Serial.println("=================================");
    Serial.println("JPEG DECODER CALLBACK");
    Serial.println("=================================");

    Serial.print("Block X: ");
    Serial.println(x);

    Serial.print("Block Y: ");
    Serial.println(y);

    Serial.print("Block width: ");
    Serial.println(w);

    Serial.print("Block height: ");
    Serial.println(h);

    Serial.print("First pixel RGB565: ");
    Serial.println(bitmap[0]);

    Serial.println("=================================");

    decoderDebugPrinted = true;
  }

  const int DECODED_WIDTH = 160;
  const int DECODED_HEIGHT = 120;

  for (uint16_t py = 0; py < h; py++)
  {
    for (uint16_t px = 0; px < w; px++)
    {
      int imageX = x + px;
      int imageY = y + py;

      if (
        imageX < 0 ||
        imageX >= DECODED_WIDTH ||
        imageY < 0 ||
        imageY >= DECODED_HEIGHT
      )
      {
        continue;
      }

      uint16_t color = bitmap[py * w + px];

      uint8_t red =
        ((color >> 11) & 0x1F) << 3;

      uint8_t green =
        ((color >> 5) & 0x3F) << 2;

      uint8_t blue =
        (color & 0x1F) << 3;

      uint16_t brightness =
        (
          (uint16_t)red * 30 +
          (uint16_t)green * 59 +
          (uint16_t)blue * 11
        ) / 100;

      int gridX =
        (imageX * GRID_WIDTH) /
        DECODED_WIDTH;

      int gridY =
        (imageY * GRID_HEIGHT) /
        DECODED_HEIGHT;

      if (gridX >= GRID_WIDTH)
      {
        gridX = GRID_WIDTH - 1;
      }

      if (gridY >= GRID_HEIGHT)
      {
        gridY = GRID_HEIGHT - 1;
      }

      int index =
        gridY * GRID_WIDTH + gridX;

      gridBrightnessSum[index] += brightness;
      gridPixelCount[index]++;
    }
  }

  return true;
}

// =====================================================
// Create Fingerprint
// =====================================================

bool createFingerprint(
  camera_fb_t *fb,
  float *fingerprint
)
{
  if (
    fb == nullptr ||
    fb->buf == nullptr ||
    fb->len < 100 ||
    fingerprint == nullptr
  )
  {
    Serial.println("Invalid frame for fingerprint.");
    return false;
  }

  clearFingerprintBuffers();

  fingerprintDecodeActive = true;

  JRESULT result =
    TJpgDec.drawJpg(
      0,
      0,
      fb->buf,
      fb->len
    );

  fingerprintDecodeActive = false;

  if (result != JDR_OK)
  {
    Serial.print(
      "JPEG fingerprint decode failed. Error: "
    );

    Serial.println((int)result);

    return false;
  }

  int validCells = 0;

  for (int i = 0; i < GRID_CELLS; i++)
  {
    if (gridPixelCount[i] > 0)
    {
      fingerprint[i] =
        float(gridBrightnessSum[i]) /
        float(gridPixelCount[i]);

      validCells++;
    }
    else
    {
      fingerprint[i] = 0.0;
    }
  }

  if (!fingerprintInfoPrinted)
  {
    Serial.print("Fingerprint valid cells: ");
    Serial.print(validCells);
    Serial.print("/");
    Serial.println(GRID_CELLS);

    fingerprintInfoPrinted = true;
  }

  if (validCells == 0)
  {
    Serial.println(
      "ERROR: Decoder returned no pixels."
    );

    return false;
  }

  return true;
}

// =====================================================
// Capture Background Reference
// =====================================================

void captureBackgroundReference(
  camera_fb_t *fb
)
{
  Serial.println();
  Serial.println("Creating background reference...");

  fingerprintInfoPrinted = false;

  if (
    createFingerprint(
      fb,
      backgroundFingerprint
    )
  )
  {
    backgroundReady = true;

    Serial.println(
      "Background reference created."
    );
  }
  else
  {
    backgroundReady = false;

    Serial.println(
      "ERROR: Could not create background."
    );
  }
}

// =====================================================
// Calculate Visual Change
// =====================================================

float calculateChange(
  camera_fb_t *fb
)
{
  if (
    !backgroundReady ||
    fb == nullptr ||
    fb->buf == nullptr ||
    fb->len < 100
  )
  {
    return 0.0;
  }

  if (
    !createFingerprint(
      fb,
      currentFingerprint
    )
  )
  {
    return 0.0;
  }

  float totalDifference = 0.0;

  for (int i = 0; i < GRID_CELLS; i++)
  {
    float difference =
      fabs(
        currentFingerprint[i] -
        backgroundFingerprint[i]
      );

    totalDifference += difference;
  }

  float averageDifference =
    totalDifference / GRID_CELLS;

  float changePercentage =
    (averageDifference / 255.0) * 100.0;

  if (changePercentage < 0.0)
  {
    changePercentage = 0.0;
  }

  if (changePercentage > 100.0)
  {
    changePercentage = 100.0;
  }

  return changePercentage;
}

// =====================================================
// Object Present
// =====================================================

bool isObjectPresent(
  camera_fb_t *fb
)
{
  float change =
    calculateChange(fb);

  Serial.print("Visual change: ");
  Serial.print(change, 1);
  Serial.println("%");

  if (change >= CHANGE_THRESHOLD)
  {
    detectionConfirmCount++;

    Serial.print("Object confirmation: ");
    Serial.print(detectionConfirmCount);
    Serial.print("/");
    Serial.println(DETECTION_CONFIRMATIONS);

    if (
      detectionConfirmCount >=
      DETECTION_CONFIRMATIONS
    )
    {
      detectionConfirmCount = 0;
      uploadCameraEventToLiveMonitor("object_detected", nullptr, 0.0f);
      return true;
    }
  }
  else
  {
    detectionConfirmCount = 0;
  }

  return false;
}

// =====================================================
// Object Removed
// =====================================================

bool isObjectRemoved(
  camera_fb_t *fb
)
{
  float change =
    calculateChange(fb);

  Serial.print("Removal change: ");
  Serial.print(change, 1);
  Serial.println("%");

  if (change <= REMOVAL_THRESHOLD)
  {
    removalConfirmCount++;

    Serial.print("Removal confirmation: ");
    Serial.print(removalConfirmCount);
    Serial.print("/");
    Serial.println(REMOVAL_CONFIRMATIONS);

    if (
      removalConfirmCount >=
      REMOVAL_CONFIRMATIONS
    )
    {
      removalConfirmCount = 0;
      return true;
    }
  }
  else
  {
    removalConfirmCount = 0;
  }

  return false;
}

// =====================================================
// Detection State Machine
// =====================================================

bool detectObjectChange(
  camera_fb_t *fb
)
{
  if (fb == nullptr)
  {
    return false;
  }

  if (
    detectionState ==
    WAITING_FOR_OBJECT
  )
  {
    if (isObjectPresent(fb))
    {
      printSeparator();

      Serial.println("OBJECT DETECTED");
      Serial.println("Camera capture triggered.");
      Serial.println("=================================");

      detectionState =
        WAITING_FOR_REMOVAL;

      return true;
    }
  }
  else if (
    detectionState ==
    WAITING_FOR_REMOVAL
  )
  {
    if (isObjectRemoved(fb))
    {
      printSeparator();

      Serial.println("OBJECT REMOVED");
      Serial.println("SYSTEM READY");
      Serial.println("Waiting for next object...");
      Serial.println("=================================");

      uploadCameraEventToLiveMonitor("object_removed", nullptr, 0.0f);

      detectionState =
        WAITING_FOR_OBJECT;

      detectionConfirmCount = 0;
      removalConfirmCount = 0;
    }
  }

  return false;
}

// =====================================================
// Send Image To Local AI Server
// =====================================================

bool sendImageToAI(
  camera_fb_t *fb,
  String *categoryOut,
  float *confidenceOut
)
{
  if (
    fb == nullptr ||
    fb->buf == nullptr ||
    fb->len == 0
  )
  {
    Serial.println(
      "ERROR: Invalid camera frame."
    );

    return false;
  }

  printSeparator();

  Serial.println("SENDING IMAGE TO AI");
  Serial.println("=================================");

  Serial.print("Image size: ");
  Serial.print(fb->len);
  Serial.println(" bytes");

  // ---------------------------------------------------
  // Wi-Fi
  // ---------------------------------------------------

  if (WiFi.status() != WL_CONNECTED)
  {
    ensureWiFiConnected();
  }

  if (WiFi.status() != WL_CONNECTED)
  {
    Serial.println(
      "ERROR: Wi-Fi unavailable."
    );

    return false;
  }

  Serial.print("ESP32-CAM IP: ");
  Serial.println(WiFi.localIP());

  Serial.print("AI server: ");
  Serial.print(AI_SERVER);
  Serial.print(":");
  Serial.println(AI_PORT);

  Serial.print("AI endpoint: ");
  Serial.println(AI_PATH);

  // ---------------------------------------------------
  // Connect
  // ---------------------------------------------------

  WiFiClient client;

  unsigned long connectStart = millis();

  Serial.println("Connecting to AI server...");

  if (
    !client.connect(
      AI_SERVER,
      AI_PORT
    )
  )
  {
    Serial.print(
      "ERROR: Could not connect to AI server."
    );

    Serial.print(
      " Connection time: "
    );

    Serial.print(
      millis() - connectStart
    );

    Serial.println(" ms");

    return false;
  }

  Serial.print(
    "AI server connected in "
  );

  Serial.print(
    millis() - connectStart
  );

  Serial.println(" ms");

  // ---------------------------------------------------
  // Multipart form data
  // ---------------------------------------------------

  String boundary =
    "----ATBCameraBoundary";

  String partHeader =
    "--" + boundary + "\r\n"
    "Content-Disposition: form-data; name=\"image\"; filename=\"camera.jpg\"\r\n"
    "Content-Type: image/jpeg\r\n\r\n";

  String partFooter =
    "\r\n--" +
    boundary +
    "--\r\n";

  size_t contentLength =
    partHeader.length() +
    fb->len +
    partFooter.length();

  // ---------------------------------------------------
  // HTTP request
  // ---------------------------------------------------

  client.print("POST ");
  client.print(AI_PATH);
  client.println(" HTTP/1.1");

  client.print("Host: ");
  client.print(AI_SERVER);
  client.print(":");
  client.println(AI_PORT);

  client.println("Connection: close");

  client.print(
    "Content-Type: multipart/form-data; boundary="
  );

  client.println(boundary);

  client.print("Content-Length: ");
  client.println(contentLength);

  client.println();

  // ---------------------------------------------------
  // Send multipart header
  // ---------------------------------------------------

  client.print(partHeader);

  // ---------------------------------------------------
  // Send JPEG
  // ---------------------------------------------------

  size_t totalWritten = 0;

  const size_t CHUNK_SIZE = 1024;

  while (totalWritten < fb->len)
  {
    size_t remaining =
      fb->len - totalWritten;

    size_t chunk =
      remaining > CHUNK_SIZE
        ? CHUNK_SIZE
        : remaining;

    size_t written =
      client.write(
        fb->buf + totalWritten,
        chunk
      );

    if (written == 0)
    {
      Serial.println(
        "ERROR: JPEG transmission failed."
      );

      client.stop();
      return false;
    }

    totalWritten += written;

    delay(1);
  }

  Serial.print("JPEG bytes sent: ");
  Serial.print(totalWritten);
  Serial.println(" bytes");

  if (totalWritten != fb->len)
  {
    Serial.println(
      "ERROR: JPEG transmission incomplete."
    );

    client.stop();
    return false;
  }

  // ---------------------------------------------------
  // Send multipart footer
  // ---------------------------------------------------

  client.print(partFooter);

  // ---------------------------------------------------
  // Wait for response
  // ---------------------------------------------------

  unsigned long responseStart =
    millis();

  while (
    !client.available() &&
    client.connected() &&
    millis() - responseStart <
      AI_RESPONSE_TIMEOUT
  )
  {
    delay(5);
  }

  if (!client.available())
  {
    Serial.println(
      "ERROR: AI server response timeout."
    );

    client.stop();
    return false;
  }

  // ---------------------------------------------------
  // Read HTTP status
  // ---------------------------------------------------

  String statusLine =
    client.readStringUntil('\n');

  statusLine.trim();

  Serial.print("HTTP status: ");
  Serial.println(statusLine);

  // ---------------------------------------------------
  // Read headers
  // ---------------------------------------------------

  while (client.connected())
  {
    String line =
      client.readStringUntil('\n');

    line.trim();

    if (line.length() == 0)
    {
      break;
    }
  }

  // ---------------------------------------------------
  // Read response body
  // ---------------------------------------------------

  String response = "";

  unsigned long lastData =
    millis();

  while (
    client.connected() ||
    client.available()
  )
  {
    while (client.available())
    {
      char c =
        client.read();

      response += c;

      lastData =
        millis();
    }

    if (
      millis() - lastData >
      3000
    )
    {
      break;
    }

    delay(1);
  }

  client.stop();

  response.trim();

  Serial.println();
  Serial.println("AI response:");
  Serial.println(response);

  // ---------------------------------------------------
  // Empty response
  // ---------------------------------------------------

  if (response.length() == 0)
  {
    Serial.println(
      "ERROR: Empty AI response."
    );

    return false;
  }

  // ---------------------------------------------------
  // Parse JSON
  // ---------------------------------------------------

  JsonDocument doc;

  DeserializationError error =
    deserializeJson(
      doc,
      response
    );

  if (error)
  {
    Serial.print(
      "JSON parse error: "
    );

    Serial.println(
      error.c_str()
    );

    return false;
  }

  // ---------------------------------------------------
  // Server success
  // ---------------------------------------------------

  bool success =
    doc["success"] | false;

  if (!success)
  {
    const char *errorMessage =
      doc["error"];

    Serial.print(
      "AI server error: "
    );

    if (errorMessage != nullptr)
    {
      Serial.println(errorMessage);
    }
    else
    {
      Serial.println("Unknown error.");
    }

    return false;
  }

  // ---------------------------------------------------
  // Prediction
  // ---------------------------------------------------

  const char *category =
    doc["category"];

  float confidence =
    doc["confidence"] | 0.0;

  if (category == nullptr)
  {
    Serial.println(
      "ERROR: No category in AI response."
    );

    return false;
  }

  if (categoryOut != nullptr)
  {
    *categoryOut = category;
  }
  if (confidenceOut != nullptr)
  {
    *confidenceOut = confidence;
  }

  // ---------------------------------------------------
  // Display result
  // ---------------------------------------------------

  printSeparator();

  Serial.println("AI PREDICTION");
  Serial.println("=================================");

  Serial.print("Category: ");
  Serial.println(category);

  Serial.print("Confidence: ");
  Serial.print(
    confidence * 100.0,
    2
  );

  Serial.println("%");

  Serial.println(
    "================================="
  );

  return true;
}

bool uploadCameraEventToLiveMonitor(
  const char *eventName,
  const char *category,
  float confidence
)
{
  if (
    eventName == nullptr ||
    CAMERA_API_KEY[0] == '\0' ||
    strcmp(CAMERA_API_KEY, "PASTE_CAMERA_API_KEY_HERE") == 0 ||
    WiFi.status() != WL_CONNECTED ||
    time(nullptr) < 1700000000
  )
  {
    return false;
  }

  WiFiClientSecure client;
  client.setCACert(CAMERA_ROOT_CA);
  client.setTimeout(10000);

  if (!client.connect(CAMERA_UPLOAD_HOST, 443, 10000))
  {
    Serial.println("Camera event upload failed: HTTPS connection failed.");
    return false;
  }

  JsonDocument payload;
  payload["event"] = eventName;
  if (category != nullptr && category[0] != '\0')
  {
    payload["category"] = category;
    payload["confidence"] = confidence;
  }

  String body;
  serializeJson(payload, body);

  client.print("POST ");
  client.print(CAMERA_UPLOAD_PATH);
  client.println(" HTTP/1.1");
  client.print("Host: ");
  client.println(CAMERA_UPLOAD_HOST);
  client.print("X-API-KEY: ");
  client.println(CAMERA_API_KEY);
  client.println("Content-Type: application/json");
  client.print("Content-Length: ");
  client.println(body.length());
  client.println("Connection: close");
  client.println();
  client.print(body);

  unsigned long responseStart = millis();
  while (!client.available() && client.connected() && millis() - responseStart < 10000)
  {
    delay(10);
  }

  if (!client.available())
  {
    Serial.println("Camera event upload failed: server response timed out.");
    client.stop();
    return false;
  }

  String statusLine = client.readStringUntil('\n');
  statusLine.trim();
  Serial.print("Camera event HTTP status: ");
  Serial.println(statusLine);
  bool accepted = statusLine.startsWith("HTTP/1.1 2") || statusLine.startsWith("HTTP/1.0 2");
  client.stop();
  return accepted;
}

// =====================================================
// Upload a JPEG snapshot to the live monitor
// =====================================================

bool uploadSnapshotToLiveMonitor(
  camera_fb_t *fb
)
{
  if (
    fb == nullptr ||
    fb->buf == nullptr ||
    fb->len == 0 ||
    fb->len > 2 * 1024 * 1024
  )
  {
    Serial.println("Camera upload skipped: invalid or oversized JPEG.");
    return false;
  }

  if (
    CAMERA_API_KEY[0] == '\0' ||
    strcmp(CAMERA_API_KEY, "PASTE_CAMERA_API_KEY_HERE") == 0
  )
  {
    Serial.println("Camera upload skipped: set CAMERA_API_KEY in the sketch.");
    return false;
  }

  if (time(nullptr) < 1700000000)
  {
    Serial.println("Camera upload skipped: system clock is not synchronized.");
    return false;
  }

  WiFiClientSecure client;
  client.setCACert(CAMERA_ROOT_CA);
  client.setTimeout(10000);

  Serial.println("Uploading camera snapshot to CT-ABS...");

  if (!client.connect(CAMERA_UPLOAD_HOST, 443, 10000))
  {
    Serial.println("Camera upload failed: HTTPS connection failed.");
    return false;
  }

  const char *boundary = "----CTABCameraBoundary7MA4YWxkTrZu0gW";
  String partHeader =
    String("--") + boundary + "\r\n"
    "Content-Disposition: form-data; name=\"image\"; filename=\"camera.jpg\"\r\n"
    "Content-Type: image/jpeg\r\n\r\n";
  String partFooter = String("\r\n--") + boundary + "--\r\n";
  size_t contentLength = partHeader.length() + fb->len + partFooter.length();

  client.print("POST ");
  client.print(CAMERA_UPLOAD_PATH);
  client.println(" HTTP/1.1");
  client.print("Host: ");
  client.println(CAMERA_UPLOAD_HOST);
  client.print("X-API-KEY: ");
  client.println(CAMERA_API_KEY);
  client.print("Content-Type: multipart/form-data; boundary=");
  client.println(boundary);
  client.print("Content-Length: ");
  client.println(contentLength);
  client.println("Connection: close");
  client.println();
  client.print(partHeader);

  size_t sent = 0;
  while (sent < fb->len)
  {
    size_t chunkSize = min((size_t)1024, fb->len - sent);
    size_t written = client.write(fb->buf + sent, chunkSize);
    if (written == 0)
    {
      Serial.println("Camera upload failed while sending JPEG data.");
      client.stop();
      return false;
    }
    sent += written;
  }

  client.print(partFooter);

  unsigned long responseStart = millis();
  while (
    !client.available() &&
    client.connected() &&
    millis() - responseStart < 15000
  )
  {
    delay(10);
  }

  if (!client.available())
  {
    Serial.println("Camera upload failed: server response timed out.");
    client.stop();
    return false;
  }

  String statusLine = client.readStringUntil('\n');
  statusLine.trim();
  Serial.print("Camera upload HTTP status: ");
  Serial.println(statusLine);

  while (client.connected())
  {
    String line = client.readStringUntil('\n');
    line.trim();
    if (line.length() == 0)
    {
      break;
    }
  }

  String response = client.readString();
  response.trim();
  if (response.length() > 0)
  {
    Serial.println(response);
  }

  bool accepted = statusLine.startsWith("HTTP/1.1 2") || statusLine.startsWith("HTTP/1.0 2");
  client.stop();
  return accepted;
}

// =====================================================
// SETUP
// =====================================================

void setup()
{
  Serial.begin(115200);

  Serial.setDebugOutput(true);

  delay(500);

  Serial.println();
  Serial.println();

  printSeparator();

  Serial.println(
    "ESP32-CAM SMARTBIN"
  );

  Serial.println(
    "CAMERA + LOCAL AI DETECTION"
  );

  Serial.println(
    "================================="
  );

  // ===================================================
  // Camera configuration
  // ===================================================

  camera_config_t config;

  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;

  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;

  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;

  config.pin_sccb_sda = SIOD_GPIO_NUM;
  config.pin_sccb_scl = SIOC_GPIO_NUM;

  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;

  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;

  if (psramFound())
  {
    Serial.println(
      "PSRAM detected."
    );

    config.frame_size = FRAMESIZE_VGA;
    config.jpeg_quality = 12;
    config.fb_count = 2;
    config.grab_mode = CAMERA_GRAB_LATEST;
    config.fb_location = CAMERA_FB_IN_PSRAM;
  }
  else
  {
    Serial.println(
      "WARNING: PSRAM not detected."
    );

    config.frame_size = FRAMESIZE_QVGA;
    config.jpeg_quality = 14;
    config.fb_count = 1;
    config.grab_mode = CAMERA_GRAB_WHEN_EMPTY;
    config.fb_location = CAMERA_FB_IN_DRAM;
  }

  Serial.println(
    "Initializing camera..."
  );

  esp_err_t err =
    esp_camera_init(&config);

  if (err != ESP_OK)
  {
    Serial.printf(
      "Camera initialization failed: 0x%x\n",
      err
    );

    while (true)
    {
      delay(1000);
    }
  }

  Serial.println(
    "Camera initialized successfully."
  );

  sensor_t *s =
    esp_camera_sensor_get();

  if (s != nullptr)
  {
    s->set_framesize(
      s,
      FRAMESIZE_VGA
    );

    s->set_quality(
      s,
      12
    );

    Serial.println(
      "Camera sensor configured."
    );
  }

  setupLedFlash();

  // ===================================================
  // JPEG decoder
  // ===================================================

  Serial.println(
    "Initializing JPEG decoder..."
  );

  TJpgDec.setJpgScale(4);

  TJpgDec.setCallback(
    jpegFingerprintCallback
  );

  Serial.println(
    "JPEG decoder ready."
  );

  // ===================================================
  // Wi-Fi
  // ===================================================

  Serial.println();
  Serial.println(
    "Connecting to Wi-Fi..."
  );

  WiFi.mode(WIFI_STA);

  WiFi.setSleep(false);

  WiFi.begin(
    ssid,
    password
  );

  int attempts = 0;

  while (
    WiFi.status() != WL_CONNECTED
  )
  {
    delay(500);

    Serial.print(".");

    attempts++;

    if (attempts >= 60)
    {
      Serial.println();

      Serial.println(
        "Wi-Fi connection failed."
      );

      while (true)
      {
        delay(1000);
      }
    }
  }

  Serial.println();

  Serial.println(
    "Wi-Fi connected."
  );

  Serial.println("Synchronizing clock for HTTPS certificate checks...");
  configTime(0, 0, "pool.ntp.org", "time.nist.gov");
  time_t currentTime = time(nullptr);
  unsigned long clockSyncStart = millis();
  while (
    currentTime < 1700000000 &&
    millis() - clockSyncStart < 10000
  )
  {
    delay(250);
    currentTime = time(nullptr);
  }
  Serial.println(
    currentTime >= 1700000000
      ? "Clock synchronized."
      : "Clock not synchronized yet; live uploads will retry later."
  );

  // ===================================================
  // Network information
  // ===================================================

  printSeparator();

  Serial.println(
    "NETWORK INFORMATION"
  );

  Serial.println(
    "================================="
  );

  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());

  Serial.print("Gateway: ");
  Serial.println(WiFi.gatewayIP());

  Serial.print("Signal strength: ");
  Serial.print(WiFi.RSSI());
  Serial.println(" dBm");

  Serial.print("AI server: ");
  Serial.print(AI_SERVER);
  Serial.print(":");
  Serial.println(AI_PORT);

  Serial.print("AI endpoint: ");
  Serial.println(AI_PATH);

  Serial.print("Detection interval: ");
  Serial.print(DETECTION_INTERVAL);
  Serial.println(" ms");

  // ===================================================
  // Camera web server
  // ===================================================

  Serial.println();

  Serial.println(
    "Starting camera web server..."
  );

  startCameraServer();

  Serial.println();

  printSeparator();

  Serial.println(
    "CAMERA SERVER READY"
  );

  Serial.println(
    "================================="
  );

  Serial.print(
    "Camera control page: http://"
  );

  Serial.println(
    WiFi.localIP()
  );

  Serial.print(
    "Live stream: http://"
  );

  Serial.print(
    WiFi.localIP()
  );

  Serial.println(
    ":81/stream"
  );

  Serial.println(
    "================================="
  );

  // ===================================================
  // Stabilize camera
  // ===================================================

  Serial.println();

  Serial.println(
    "Waiting for camera to stabilize..."
  );

  delay(2000);

  // ===================================================
  // Background reference
  // ===================================================

  camera_fb_t *fb =
    captureFrameWithRetry();

  if (fb != nullptr)
  {
    captureBackgroundReference(fb);

    esp_camera_fb_return(fb);
  }
  else
  {
    Serial.println(
      "ERROR: Initial camera capture failed."
    );
  }

  resetDetectionState();
  uploadCameraEventToLiveMonitor(
    backgroundReady ? "system_ready" : "camera_error",
    nullptr,
    0.0f
  );

  // ===================================================
  // Ready
  // ===================================================

  Serial.println();

  printSeparator();

  if (backgroundReady)
  {
    Serial.println(
      "SYSTEM READY"
    );

    Serial.println(
      "Waiting for object..."
    );
  }
  else
  {
    Serial.println(
      "BACKGROUND INITIALIZATION FAILED"
    );

    Serial.println(
      "Will retry automatically..."
    );
  }

  Serial.println(
    "================================="
  );
}

// =====================================================
// LOOP
// =====================================================

void loop()
{
  if (
    millis() - lastWiFiCheck >=
    WIFI_CHECK_INTERVAL
  )
  {
    lastWiFiCheck = millis();

    ensureWiFiConnected();
  }

  if (
    millis() - lastDetectionCheck >=
    DETECTION_INTERVAL
  )
  {
    lastDetectionCheck = millis();

    unsigned long captureStart =
      millis();

    camera_fb_t *fb =
      captureFrameWithRetry();

    unsigned long captureTime =
      millis() - captureStart;

    if (fb == nullptr)
    {
      delay(10);
      return;
    }

    if (millis() - lastCameraUpload >= CAMERA_UPLOAD_INTERVAL)
    {
      lastCameraUpload = millis();
      uploadSnapshotToLiveMonitor(fb);
    }

    if (!backgroundReady)
    {
      captureBackgroundReference(fb);

      esp_camera_fb_return(fb);

      delay(100);

      return;
    }

    if (
      detectionState ==
      WAITING_FOR_OBJECT
    )
    {
      if (detectObjectChange(fb))
      {
        Serial.println("Object detected: uploading event snapshot.");
        uploadSnapshotToLiveMonitor(fb);
        lastCameraUpload = millis();

        Serial.print(
          "Camera capture time: "
        );

        Serial.print(
          captureTime
        );

        Serial.println(
          " ms"
        );

        String aiCategory;
        float aiConfidence = 0.0f;
        bool aiSuccess =
          sendImageToAI(fb, &aiCategory, &aiConfidence);

        if (aiSuccess)
        {
          uploadCameraEventToLiveMonitor("ai_result", aiCategory.c_str(), aiConfidence);

          Serial.println();

          Serial.println(
            "AI classification completed."
          );

          Serial.println(
            "Waiting for object removal..."
          );
        }
        else
        {
          uploadCameraEventToLiveMonitor("ai_failed", nullptr, 0.0f);

          Serial.println();

          Serial.println(
            "AI classification failed."
          );

          Serial.println(
            "Returning to object detection."
          );

          resetDetectionState();
        }
      }
    }
    else if (
      detectionState ==
      WAITING_FOR_REMOVAL
    )
    {
      detectObjectChange(fb);
    }

    esp_camera_fb_return(fb);
  }

  delay(1);c:\Users\admin\Documents\Arduino\sketch_sep8a\sketch_sep8a.ino
}

