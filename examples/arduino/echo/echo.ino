/*
 * Arduino Echo Test
 *
 * PHPからの文字列を受信し、そのまま返信する
 * PHP側のbasic.phpと組み合わせて使用
 */

void setup() {
  // シリアル通信を9600bpsで開始
  Serial.begin(9600);

  // 起動待機
  while (!Serial) {
    ; // シリアルポートの接続を待つ
  }

  // Serial.println("Arduino ready!");
}

void loop() {
  // データが受信されているかチェック
  if (Serial.available() > 0) {
    // 1行読み込み
    String received = Serial.readStringUntil('\n');

    // 受信した内容をそのまま返信
    Serial.print("Echo: ");
    Serial.println(received);
  }
}