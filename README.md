inern2014w
===
## 環境構築後
score:1512(以下，スコアはworkload=1時の計測とする)

## PHPの変更
* Lemonadeがオーバヘッドになっていると考え，フレームワークを使わずに一から書き直した．
** オーバヘッドになってしまうと考えた為，クラス等を使用せずにベタ書きした．

score:1631

## 環境設定 

### nginxの設定変更
* CPUのコア数が4つなので，nginxのworker_processを4にした．

score:1674

### MySQLテーブル調整

* データアクセス高速化の為に，`isu4_qualifier.login_log` の `ip` に インデックスを貼る．
* 同じ理由で，すべてのテーブルのドメインを見直す．
** `users(mediumint,varchar(24),varchar(64),varchar(9))`
** `login_log(mediumint,datetime,mediumint,varchar(21),varchar(14),tinyint)`

score:3714

~                                                                                            
## その他(反省)
* オーバヘッドが多いSQLの見直しを行おうと思ったが，時間がなくなってしまった
** ストアドプロシージャ等を用いて，SQLパースのコストを下げるべきだった。

* 脱Lemonadeばかりに注力してしまった．

~                                                                                                     
~                                                                                                     
~                                                                                                     
~                           
