<?php
/** Non-destructive, multi-factor incident similarity engine. */
require_once __DIR__ . '/../config/db.php';

class ClusteringEngine {
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $r = 6371000; $dLat = deg2rad($lat2-$lat1); $dLon = deg2rad($lon2-$lon1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
        return 2*$r*atan2(sqrt($a), sqrt(max(0, 1-$a)));
    }

    public static function calculateTextSimilarity(string $a, string $b, array $stopwords=[]): float {
        $tokens = static function(string $s) use ($stopwords): array {
            $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
            $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
            $words = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY); $out=[];
            foreach ($words as $w) if ((function_exists('mb_strlen') ? mb_strlen($w, 'UTF-8') : strlen($w))>=3 && !in_array($w,$stopwords,true)) $out[$w]=true;
            return array_keys($out);
        };
        $aa=$tokens($a); $bb=$tokens($b); if (!$aa || !$bb) return 0.0;
        return count(array_intersect($aa,$bb))/max(1,count(array_unique(array_merge($aa,$bb))));
    }

    private static function categoriesCompatible(array $a, array $b, array $cfg): bool {
        $x=(int)$a['category_id']; $y=(int)$b['category_id']; if ($x===$y) return true;
        foreach (($cfg['compatible_categories'] ?? []) as $pair) if (count($pair)>=2 && (($x==(int)$pair[0] && $y==(int)$pair[1]) || ($y==(int)$pair[0] && $x==(int)$pair[1]))) return true;
        return false;
    }

    public static function evaluateSimilarity(array $a, array $b, array $cfg=[]): array {
        $cfg = $cfg ?: require __DIR__.'/../config/clustering.php';
        if (!self::categoriesCompatible($a,$b,$cfg)) return ['is_similar'=>false,'score'=>0,'reason'=>'Incompatible incident categories'];
        $ta=strtotime($a['incident_time'] ?? $a['created_at'] ?? 'now'); $tb=strtotime($b['incident_time'] ?? $b['created_at'] ?? 'now');
        $hours=abs($ta-$tb)/3600;
        if ($hours > $cfg['max_time_diff_hours']) return ['is_similar'=>false,'score'=>0,'reason'=>'Reports fall outside configured time window'];
        $hasA=isset($a['latitude'],$a['longitude']) && $a['latitude']!==null && $a['longitude']!==null;
        $hasB=isset($b['latitude'],$b['longitude']) && $b['latitude']!==null && $b['longitude']!==null;
        $distance=null;
        if ($hasA && $hasB) {
            $distance=self::haversineDistance((float)$a['latitude'],(float)$a['longitude'],(float)$b['latitude'],(float)$b['longitude']);
            if ($distance > $cfg['max_distance_meters']) return ['is_similar'=>false,'score'=>0,'reason'=>'Reports exceed geographic distance threshold'];
        }
        $sameSociety=!empty($a['society_id']) && !empty($b['society_id']) && (int)$a['society_id']===(int)$b['society_id'];
        // Without coordinates, a shared neighbourhood is mandatory; proximity alone never suffices.
        if ($distance===null && !$sameSociety) return ['is_similar'=>false,'score'=>0,'reason'=>'Insufficient location evidence'];
        $w=$cfg['weights']; $score=$w['category_match']; $factors=['Compatible category'];
        if ($distance!==null) { $p=max(0,1-$distance/max(1,$cfg['max_distance_meters']))*$w['distance_score']; $score+=$p; $factors[]='Distance: '.round($distance).' m'; }
        else { $p=$w['distance_score']*0.35; $score+=$p; $factors[]='GPS unavailable; shared neighbourhood used'; }
        $tp=max(0,1-$hours/max(1,$cfg['max_time_diff_hours']))*$w['time_score']; $score+=$tp; $factors[]='Time gap: '.round($hours,1).' hours';
        $sim=self::calculateTextSimilarity(($a['title']??'').' '.($a['description']??''),($b['title']??'').' '.($b['description']??''),$cfg['stopwords']??[]);
        $xp=$sim*$w['text_similarity']; $score+=$xp; $factors[]='Description keyword similarity: '.round($sim*100).'%';
        if ($sameSociety) { $score+=$w['neighbourhood_match']; $factors[]='Same society / neighbourhood'; }
        $score=round(min(100,$score),1);
        // Multi-factor guard: category + at least two meaningful supporting signals.
        $support=($sameSociety?1:0)+(($distance!==null && $distance <= $cfg['max_distance_meters']*0.5)?1:0)+($sim>=0.12?1:0)+($hours <= $cfg['max_time_diff_hours']*0.5?1:0);
        $ok=$score >= $cfg['min_similarity_score'] && $support>=2 && ($sim>=0.08 || $sameSociety);
        return ['is_similar'=>$ok,'score'=>$score,'distance_meters'=>$distance===null?null:round($distance),'time_diff_hours'=>round($hours,1),'text_similarity'=>round($sim,3),'factors'=>$factors,'reason'=>$ok?'Multiple similarity signals meet configured thresholds':'Insufficient combined similarity signals'];
    }

    public static function processIncidentClustering(PDO $pdo, int $incidentId): ?int {
        $s=$pdo->prepare('SELECT * FROM incidents WHERE id=?'); $s->execute([$incidentId]); $current=$s->fetch(); if (!$current) return null;
        $cfg=require __DIR__.'/../config/clustering.php';
        $q=$pdo->prepare("SELECT * FROM incidents WHERE id<>? AND status<>'resolved' AND incident_time BETWEEN DATE_SUB(?, INTERVAL {$cfg['max_time_diff_hours']} HOUR) AND DATE_ADD(?, INTERVAL {$cfg['max_time_diff_hours']} HOUR) ORDER BY created_at DESC LIMIT 100");
        $q->execute([$incidentId,$current['incident_time'],$current['incident_time']]); $matches=[];
        foreach ($q->fetchAll() as $candidate) { $ev=self::evaluateSimilarity($current,$candidate,$cfg); if ($ev['is_similar']) $matches[]=['incident'=>$candidate,'evaluation'=>$ev]; }
        if (!$matches) return null;
        // Prefer a suggested/approved cluster already containing the strongest match.
        usort($matches,fn($x,$y)=>$y['evaluation']['score']<=>$x['evaluation']['score']);
        $pdo->beginTransaction();
        try {
            // Only extend an existing *suggested* cluster when the strongest match
            // belongs to it. Never silently add new reports to an admin-approved group.
            $best=$matches[0];
            $find=$pdo->prepare("SELECT c.id FROM cluster_reports cr JOIN incident_clusters c ON c.id=cr.cluster_id WHERE cr.incident_id=? AND c.status='suggested' ORDER BY c.id LIMIT 1");
            $find->execute([(int)$best['incident']['id']]); $existing=$find->fetch();
            $clusterId=$existing ? (int)$existing['id'] : null;
            if (!$clusterId) {
                $i=$best['incident']; $first=min($current['incident_time'],$i['incident_time']); $latest=max($current['incident_time'],$i['incident_time']);
                $lat=$current['latitude']!==null ? $current['latitude'] : $i['latitude']; $lng=$current['longitude']!==null ? $current['longitude'] : $i['longitude'];
                $ins=$pdo->prepare("INSERT INTO incident_clusters(title,category_id,approx_latitude,approx_longitude,approx_location_text,first_report_time,latest_report_time,status) VALUES(?,?,?,?,?,?,?,'suggested')");
                $ins->execute(['Potentially related: '.(function_exists('mb_substr') ? mb_substr($current['title'],0,165,'UTF-8') : substr($current['title'],0,165)),$current['category_id'],$lat,$lng,$current['location_text'],$first,$latest]); $clusterId=(int)$pdo->lastInsertId();
                $initial=$pdo->prepare("INSERT IGNORE INTO cluster_reports(cluster_id,incident_id,similarity_score,added_by) VALUES(?,?,?,'system_auto')");
                $initial->execute([$clusterId,(int)$i['id'],$best['evaluation']['score']]);
            }
            $link=$pdo->prepare("INSERT IGNORE INTO cluster_reports(cluster_id,incident_id,similarity_score,added_by) VALUES(?,?,?,'system_auto')");
            $link->execute([$clusterId,$incidentId,$best['evaluation']['score']]);
            $upd=$pdo->prepare('UPDATE incident_clusters SET first_report_time=LEAST(first_report_time,?),latest_report_time=GREATEST(latest_report_time,?) WHERE id=?'); $upd->execute([$current['incident_time'],$current['incident_time'],$clusterId]);
            $pdo->commit(); return $clusterId;
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    public static function getClusterForIncident(PDO $pdo, int $incidentId): ?array {
        $s=$pdo->prepare("SELECT c.* FROM cluster_reports cr JOIN incident_clusters c ON c.id=cr.cluster_id WHERE cr.incident_id=? AND c.status IN ('suggested','approved') ORDER BY c.status='approved' DESC,c.id DESC LIMIT 1"); $s->execute([$incidentId]); $c=$s->fetch(); if (!$c) return null;
        $r=$pdo->prepare("SELECT i.id,i.title,i.description,i.location_text,i.latitude,i.longitude,i.incident_time,i.status,i.created_at,cat.name category_name,u.full_name reporter_name,cr.similarity_score,cr.added_by FROM cluster_reports cr JOIN incidents i ON i.id=cr.incident_id JOIN categories cat ON cat.id=i.category_id JOIN users u ON u.id=i.user_id WHERE cr.cluster_id=? ORDER BY i.incident_time ASC"); $r->execute([$c['id']]); $c['reports']=$r->fetchAll(); $c['report_count']=count($c['reports']); return $c;
    }
}
