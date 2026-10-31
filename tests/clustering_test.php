<?php
/** Run from project root: php tests/clustering_test.php */
require_once __DIR__ . '/../includes/clustering_engine.php';
$base=['category_id'=>1,'incident_time'=>'2026-10-31 10:00:00','latitude'=>26.9000,'longitude'=>75.8000,'society_id'=>2,'title'=>'Water supply stopped in Block A','description'=>'No water supply since morning in Block A'];
$similar=$base; $similar['incident_time']='2026-10-31 11:00:00'; $similar['latitude']=26.9002; $similar['title']='Water supply unavailable in Block A'; $similar['description']='Water unavailable in Block A since morning';
$far=$similar; $far['latitude']=27.5000; $differentCategory=$similar; $differentCategory['category_id']=2; $old=$similar; $old['incident_time']='2026-10-20 10:00:00';
$incorrect=$similar; $incorrect['title']='Streetlight outage'; $incorrect['description']='Streetlight is broken near the entrance'; $incorrect['society_id']=null;
$cases=[
 'similar water reports in same block'=>[ClusteringEngine::evaluateSimilarity($base,$similar)['is_similar'],true],
 'similar reports in distant neighbourhoods'=>[ClusteringEngine::evaluateSimilarity($base,$far)['is_similar'],false],
 'different categories at same location'=>[ClusteringEngine::evaluateSimilarity($base,$differentCategory)['is_similar'],false],
 'reports outside configured time window'=>[ClusteringEngine::evaluateSimilarity($base,$old)['is_similar'],false],
 'incorrect description similarity is not enough'=>[ClusteringEngine::evaluateSimilarity($base,$incorrect)['is_similar'],false],
 'admin separation is supported by moderation endpoint'=>[is_file(__DIR__.'/../api/admin/clusters.php') && strpos(file_get_contents(__DIR__.'/../api/admin/clusters.php'),"case 'remove_report': case 'separate':")!==false,true],
];
$failed=0; foreach($cases as $name=>$result){$pass=$result[0]===$result[1]; echo ($pass?'PASS':'FAIL')." - {$name}\n"; if(!$pass)$failed++;} exit($failed?1:0);
