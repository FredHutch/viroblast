<html>
<head>
<title>Sequence Download Page</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>

<body style="font-family: Courier New; font-size: 10pt">
<?php
include("include/path.inc");
set_time_limit(600);
$jobid=(empty($_GET['jobid'])) ? '' : $_GET['jobid'];
$target = (empty($_POST['target'])) ? '' : $_POST['target'];
$dldseq = (empty($_POST['dldseq'])) ? '' : $_POST['dldseq'];
$seqtype = (empty($_POST['seqtype'])) ? '' : $_POST['seqtype'];
$downloadFile = $jobid.".download.fas";
$sources = array();

$fp_log = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.log", "r") or die("Cannot open $jobid.log to read");
while(!feof($fp_log)) {
	$line = fgets($fp_log);
	$line = rtrim($line);
	if (preg_match("/Program: (\S+)/", $line, $match)) {
		$program = $match[1];
	}elseif (preg_match("/Source: (.*)/", $line, $match)) {
		$source = $match[1];
		$sources = preg_split ("/,/", $source);
	}
}
fclose($fp_log);

if ($program == "megablast" || $program == "blastn" || $program == "tblastn" || $program == "tblastx") {
	$db = "nucleotide";
}else {
	$db = "protein";
}

echo "<b><a href=download.php?jobid=$jobid&ID=$downloadFile><img src=image/download.png></a></b><br><br>";

if($dldseq) {
	$fp_parse = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.download.txt", "r") or die("Cannot open $jobid.download.txt to read");
	$target = array();
	while(!feof($fp_parse)) {
		$record = fgets($fp_parse);
		$record = rtrim($record);
		if (!$record) {
			continue;
		}
		array_push($target, $record);
	}
	fclose($fp_parse);
}

$giStatus = array();
$accNums = array();
$localSeqIds = array();
$querygiStatus = array();
$localquerySeqIds = array();
$appendFlag = 0;
for($i = 0; $i < count($target); $i++) {
	$querysbjct = "";
	list($page, $query, $sbjct, $gi, $sc) = preg_split("/\t/", $target[$i]);
	if ($gi) {
		if (preg_match("/Vector/", $sc, $match)) {
			$localSeqIds[$sbjct] = 1;
			$querysbjct = $query."-".$sbjct;
			$localquerySeqIds[$querysbjct] = 1;
		}else {
			$querysbjct = $query."-".$gi;
			if (!isset($giStatus[$gi])) {
				$giStatus[$gi] = 1;
				array_push($accNums, $gi);
				$appendFlag = 1;
			}
			if (!isset($querygiStatus[$querysbjct])) {
				$querygiStatus[$querysbjct] = 1;
				$appendFlag = 1;
			}
		}	
	}else {	# local database
		$localSeqIds[$sbjct] = 1;
		$querysbjct = $query."-".$sbjct;
		$localquerySeqIds[$querysbjct] = 1;
	}
}

if ($accNums) {
	if ($seqtype == "entire") {
		$term = join(",", $accNums);
		system("perl eutilsDownloadSeq.pl $jobid $htdocsRoot/$dataPath/$jobid $db $term");	
	}		
}

if ($localSeqIds) {
	if ($appendFlag) {
		$fp_dld = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.download.fas", "a") or die ("couldn't open $jobid.download.fas.");
	}else {
		$fp_dld = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.download.fas", "w") or die ("couldn't open $jobid.download.fas.");
	}
	if ($seqtype == "entire") {
		$dldSeqs = $dladTitles = array();
		for ($i = 0; $i < count($sources); $i++) {
			$source = $sources[$i];
			$fileFlag = 0;	
			if ($source == "HIV-1 complete genome") {
				$fileFlag = 1;
				$fp = fopen("$dbPath/nucleotide/download_lanl/hiv_complete_genome/HIV1_FLT_2017_genome_DNA.fasta", "r") or die ("couldn't open HIV1_FLT_2017_genome_DNA.fasta.");
			}elseif ($source == "HIV-1 subtype reference") {
				$fileFlag = 1;
				$fp = fopen("$dbPath/nucleotide/download_lanl/hiv_subtype_ref/HIV1_REF_2010_genome_DNA.fasta", "r") or die ("couldn't open HIV1_REF_2010_genome_DNA.fasta.");
			}elseif ($source == "HIV-1 consensus") {
				$fileFlag = 1;
				$fp = fopen("$dbPath/nucleotide/download_lanl/hiv_consensus/HIV1_CON_2002_genome_DNA.fasta", "r") or die ("couldn't open HIV1_CON_2002_genome_DNA.fas.");
			}elseif ($source == "Vector") {
				$fileFlag = 1;
				$fp = fopen("$dbPath/nucleotide/univec/vector", "r") or die ("couldn't open vector.");
			}elseif (preg_match("/File: /", $source, $match)) {
				$fileFlag = 1;
				$fp = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.blastagainst.txt", "r") or die ("couldn't open $jobid.blastagainst.txt.");
			}
		
			if ($fileFlag) {
				$flag = 0;
				while(!feof($fp)) {
					$line = fgets($fp);
					$line = rtrim($line);				
					if (preg_match("/^>(.*?)[,;\s+]/", $line, $match) || preg_match("/^>(\S+)/", $line, $match)) {					
						$seqName = $match[1];
						if (array_key_exists($seqName, $localSeqIds)) {
							$flag = 1;
							$dldTitles[$seqName] = $line;
						}else {
							$flag = 0;
						}
					}elseif ($flag) {
						$line = preg_replace("/[\-\s]/", "", $line);
						if (!array_key_exists($seqName, $dldSeqs)) {
							$dldSeqs[$seqName] = "";
						}
						$dldSeqs[$seqName] .= $line;
					}
				}
				fclose($fp);
			}
		}

		#while (list($seqName, $seq) = each($dldSeqs)) { # PHP 7.2
		foreach ($dldSeqs as $seqName => $seq) {
			$seqTitle = $dldTitles[$seqName];
			$seq = strtoupper($seq);
			fwrite($fp_dld, "$seqTitle\n");		
			while($seq) {
				$first = substr($seq, 0, 70);
				$seq = substr($seq, 70);
				fwrite($fp_dld, "$first\n");
			}
		}
	}		
	fclose($fp_dld);
}

$accName = array();
$querySeq = array();
$sbjctSeq = array();
$sbjctOri = array();
if ($seqtype == "mapping") {
	$flag = 0;
	$fp_st = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.out", "r") or die ("couldn't open $jobid.out.");
	while(!feof($fp_st)) {
		$line = fgets($fp_st);
		$line = rtrim($line);	
		if (preg_match("/^<b>Query=<\/b>\s+(.*?)[,;\s+]/", $line, $match) || preg_match("/^<b>Query=<\/b>\s+(\S+)/", $line, $match)) {
			$query = $match[1];
		}elseif (preg_match("/^><a(.*?)<\/a>\s+(.*?)([,;\s+].*)/", $line, $match) || preg_match("/^><a(.*?)<\/a>\s+(\S+)/", $line, $match)) {					
			$acc = $name = $match[2];
			if (isset($match[3])) {
				$name = $match[2].$match[3];
			}
			$queryacc = $query."-".$acc;
			if (isset($querygiStatus[$queryacc]) || isset($localquerySeqIds[$queryacc])) {
				$flag = 1;
			}else {
				$flag = 0;
			}
		}elseif ($flag == 1) {
			if (preg_match("/Length=/", $line, $match)) {
				$flag = 2;
				$accName[$acc] = $name;
			}else {
				$name .= " $line";
			}
		}elseif ($flag == 2) {	
			if (preg_match("/Score =/", $line, $match)) {
				$qstart = $qend = $sstart = $send = 0;
			}
			if (preg_match("/Strand=(.*)/", $line, $match)) {				
				$minusflag = $match[1]; 
			}
			if (preg_match("/^Query\s+(\d+)\s+(.*)\s+(\d+)$/", $line, $match)) {
				if ($qstart == 0) {
					$qstart = $match[1];
				}
				$qseq = $match[2];
				$qseq = preg_replace("/<(.*?)>/", "", $qseq);
				$qseq = preg_replace("/-+/", "", $qseq);
				$qseq = preg_replace("/\s+/", "", $qseq);
			}
			if (preg_match("/^Sbjct\s+(\d+)\s+(.*)\s+(\d+)$/", $line, $match)) {
				if ($sstart == 0) {
					$sstart = $match[1];
					if (isset($minusflag)) {
						$sbjctOri[$query][$acc][$qstart][$sstart] = $minusflag;
					}
				}
				$sseq = $match[2];
				$sseq = preg_replace("/<(.*?)>/", "", $sseq);
				$sseq = preg_replace("/-+/", "", $sseq);
				$sseq = preg_replace("/\s+/", "", $sseq);
				if (!isset($querySeq[$query][$acc][$qstart][$sstart])) {
					$querySeq[$query][$acc][$qstart][$sstart] = "";
				}
				$querySeq[$query][$acc][$qstart][$sstart] .= $qseq;
				if (!isset($sbjctSeq[$query][$acc][$qstart][$sstart])) {
					$sbjctSeq[$query][$acc][$qstart][$sstart] = "";	
				}
				$sbjctSeq[$query][$acc][$qstart][$sstart] .= $sseq;							
			}
		}
	}
	fclose($fp_st);	
	$fp_dl = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.download.fas", "w") or die ("couldn't open $jobid.download.fas.");
	foreach ($sbjctSeq as $query => $qarray) {
		foreach ($qarray as $acc => $aarray) {
			foreach ($aarray as $qstart => $qsarray) {
				foreach ($qsarray as $sstart => $sbjctseq) {
					$queryseq = $querySeq[$query][$acc][$qstart][$sstart];
					$qlen = strlen($queryseq);
					$slen = strlen($sbjctseq);
					$qend = $qstart + $qlen - 1;
					$send = $sstart + $slen - 1;
					if (isset($sbjctOri[$query][$acc][$qstart][$sstart])) {
						$mflag = $sbjctOri[$query][$acc][$qstart][$sstart];
						if ($mflag == "Plus/Minus") {
							$send = $sstart - $slen + 1;
						}
					}
					fwrite($fp_dl, ">".$accName[$acc]." (Subject: $acc, $sstart..$send; Query: $query, $qstart..$qend)\n");	
					while($sbjctseq) {
						$first = substr($sbjctseq, 0, 70);
						$sbjctseq = substr($sbjctseq, 70);
						fwrite($fp_dl, "$first\n");
					}
				}
			}
		}
	}
	fclose($fp_dl);
}

$fp_dld = fopen("$htdocsRoot/$dataPath/$jobid/$jobid.download.fas", "r") or die ("couldn't open $jobid.download.fas.");
while(!feof($fp_dld)) {
	$line = fgets($fp_dld);
	$line = rtrim($line);
	echo "$line<br>";
}
fclose($fp_dld);

?>

</body>
</html>
