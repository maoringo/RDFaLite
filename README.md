# RDFa Lite Crawler

このレポジトリは，RDFa Lite Crawler を作成＆修正＆改善するためのレポジトリです。
RDFa Liteはhtmlをマークアップし，メタデータを付与するための一形式です。
Microdataによるマークアップを取得するMicrodata.phpをもとにし，RDFa Lite用に修正しています。

##使用方法
```
php urlrdfa.php sample.html
sample.html
@prefix_freeb=http://rdf.freebase.com/ns/1
@prefix_fben=http://rdf.freebase.com/ns/3
@prefix_sagace=http://sagace.nibio.go.jp
@prefix_ov=http://open.vocab.org/terms/
@BiologicalDatabaseEntry_worksFor=Zepheira
@freeb_sample=BeeBeeBee
@prefix_fben=http://rdf.freebase.com/ns/3
@fben_gunning_fog_index=10.2
@prefix_sagace=http://sagace.nibio.go.jp
@sagace_happiness=100
@prefix_ov=http://open.vocab.org/terms/
@Person_name=Manu Sporny
@Person_telephone=1-800-555-0199
@Person_image=http://manu.sporny.org/images/manu.png
@ov_preferredAnimal=Liger
@prefix_datefben=http://rdf.freebase.com/ns/date
@datefben_orega=iarema

```
WEB版は[こちら](http://sagace.nibio.go.jp/translation/rdfalite.php)



