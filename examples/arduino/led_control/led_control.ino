/*
 * Arduino LED Control Test
 *
 * PHPからのコマンドでLEDを制御
 * PHP側のadvanced.phpと組み合わせて使用
 */

const int LED_PIN = 13; // 内蔵LEDピン
bool ledState = false;

void setup() {
  Serial.begin(115200);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  while (!Serial) {
    ;
  }

  Serial.println("LED Controller ready!");
}

void loop() {
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim(); // 前後の空白・改行を削除

    if (command == "LED_ON") {
      digitalWrite(LED_PIN, HIGH);
      ledState = true;
      Serial.println("OK: LED ON");
    }
    else if (command == "LED_OFF") {
      digitalWrite(LED_PIN, LOW);
      ledState = false;
      Serial.println("OK: LED OFF");
    }
    else if (command == "GET_STATUS") {
      if (ledState) {
        Serial.println("STATUS: LED is ON");
      } else {
        Serial.println("STATUS: LED is OFF");
      }
    }
    else if (command == "GET_TEMP") {
      // 疑似温度データ（実際のセンサーがあれば置き換える）
      float temp = 25.5 + random(-10, 10) / 10.0;
      Serial.print("TEMP: ");
      Serial.print(temp);
      Serial.println("C");
    }
    else {
      Serial.print("ERROR: Unknown command: ");
      Serial.println(command);
    }
  }
}
