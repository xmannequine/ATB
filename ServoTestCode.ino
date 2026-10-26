#include <Arduino.h>
#include <ESP32Servo.h>

// ============================================================
// Select exactly one servo for this upload
// ============================================================

constexpr uint8_t TEST_SERVO_NUMBER = 1;

constexpr uint8_t SERVO_COUNT = 5;
constexpr uint8_t SERVO_NUMBERS[SERVO_COUNT] = {1, 2, 3, 4, 5};
constexpr uint8_t SERVO_PINS[SERVO_COUNT] = {38, 4, 40, 6, 15};
constexpr int SERVO_ON_ANGLES[SERVO_COUNT] = {90, 90, 180, 140, 180};
constexpr int SERVO_OFF_ANGLES[SERVO_COUNT] = {0, 0, 0, 80, 0};
constexpr int SERVO_EXTRA_ANGLES[SERVO_COUNT] = {-1, -1, -1, 0, -1};

constexpr unsigned long SETTLE_DELAY_MS = 1000;

Servo testServo;
int testServoIndex = -1;
bool servoAttached = false;

int findServoIndex(uint8_t servoNumber)
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

bool isAllowedTestAngle(int angle)
{
  if (testServoIndex < 0)
  {
    return false;
  }

  return angle == SERVO_ON_ANGLES[testServoIndex] ||
         angle == SERVO_OFF_ANGLES[testServoIndex] ||
         angle == SERVO_EXTRA_ANGLES[testServoIndex];
}

bool moveSelectedServo(int angle)
{
  if (!isAllowedTestAngle(angle))
  {
    Serial.println("Rejected: angle is not configured for this servo.");
    return false;
  }

  if (!servoAttached)
  {
    int channel = testServo.attach(
        SERVO_PINS[testServoIndex],
        500,
        2500);

    if (channel < 0)
    {
      Serial.println("Servo attach failed.");
      return false;
    }

    servoAttached = true;
  }

  Serial.print("Moving Servo ");
  Serial.print(TEST_SERVO_NUMBER);
  Serial.print(" on GPIO ");
  Serial.print(SERVO_PINS[testServoIndex]);
  Serial.print(" to ");
  Serial.print(angle);
  Serial.println(" degrees.");

  testServo.write(angle);
  delay(SETTLE_DELAY_MS);

  Serial.println(
      "Movement command and settling delay completed; position is not feedback-confirmed.");
  return true;
}

void printInstructions()
{
  Serial.println();
  Serial.println("SERVO TEST CODE");
  Serial.println("================");
  Serial.print("Selected Servo: ");
  Serial.println(TEST_SERVO_NUMBER);
  Serial.print("GPIO: ");
  Serial.println(SERVO_PINS[testServoIndex]);
  Serial.print("ON angle: ");
  Serial.println(SERVO_ON_ANGLES[testServoIndex]);
  Serial.print("OFF angle: ");
  Serial.println(SERVO_OFF_ANGLES[testServoIndex]);

  if (SERVO_EXTRA_ANGLES[testServoIndex] >= 0)
  {
    Serial.print("Extra angle: ");
    Serial.println(SERVO_EXTRA_ANGLES[testServoIndex]);
  }

  Serial.println();
  Serial.println("Commands:");
  Serial.println("  o = configured ON angle");
  Serial.println("  f = configured OFF angle");

  if (SERVO_EXTRA_ANGLES[testServoIndex] >= 0)
  {
    Serial.println("  z = configured extra angle");
  }

  Serial.println("Only the selected servo is initialized.");
  Serial.println();
}

void setup()
{
  Serial.begin(115200);
  delay(1000);

  testServoIndex = findServoIndex(TEST_SERVO_NUMBER);

  if (testServoIndex < 0)
  {
    Serial.println("Invalid TEST_SERVO_NUMBER.");

    while (true)
    {
      delay(1000);
    }
  }

  printInstructions();
}

void loop()
{
  if (!Serial.available())
  {
    delay(20);
    return;
  }

  char command = Serial.read();

  if (command == 'o' || command == 'O')
  {
    moveSelectedServo(SERVO_ON_ANGLES[testServoIndex]);
  }
  else if (command == 'f' || command == 'F')
  {
    moveSelectedServo(SERVO_OFF_ANGLES[testServoIndex]);
  }
  else if (
      command == 'z' ||
      command == 'Z')
  {
    if (SERVO_EXTRA_ANGLES[testServoIndex] >= 0)
    {
      moveSelectedServo(SERVO_EXTRA_ANGLES[testServoIndex]);
    }
    else
    {
      Serial.println("No extra angle is configured for this servo.");
    }
  }
}
