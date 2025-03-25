<html>
<head><title>ViroBLAST Result Page</title>
<link href="stylesheets/viroblast.css"  rel="Stylesheet" type="text/css" />
<link rel="stylesheet" href="/stylesheets/spin.css">
<script type="text/javascript" src='javascripts/sorttable.js'></script>
<script type="text/javascript">
	function checkform(form) {
		var checkbox_checked = 0;
		if (form.dldseq.checked) {
			checkbox_checked++;
		}else {
			for (var i = 0; i < form.checkedSeq.length; i++) {
				if (form.checkedSeq[i].checked) {
					checkbox_checked++;
					break;
				}
			}
		}
		
		if (checkbox_checked == 0) {
			alert ("Please check the sequence(s) you want to download");
			return false;
		}
		return true;
	}
</script>
</head>
<body>
    <div id="header">
	    <div class="spacer">&nbsp;</div>    
		<span class="logo"><a name="top"></a>ViroBLAST Result</span>   
    </div>
    
    <div id="nav">	
		<span class='nav'><a href="viroblast.php" class="nav">Home</a></span>
		<span class='nav'><a href=docs/aboutviroblast.html class="nav">About ViroBLAST</a></span>
		<span class='nav'><a href=docs/contact.html class="nav">Contact</a></span>
		<span class='nav'><a href=docs/viroblasthelp.html class="nav">Help</a></span>
		<span class='nav'><a href=https://github.com/MullinsLab/ViroblastStandalone class="nav">Download</a></span>
		&nbsp;
	</div>
	
	<div class="spacer">&nbsp;</div>
    
    <div id="indent">


<?php

include("include/path.inc");
set_time_limit(600);
$jobid = (empty($_GET['jobid'])) ? '' : $_GET['jobid'];
$patientIDarray=(empty($_POST['patientIDarray'])) ? array() : $_POST['patientIDarray'];
$opt = (empty($_GET['opt'])) ? '' : $_GET['opt'];
$blast_flag=(empty($_POST['blast_flag'])) ? '' : $_POST['blast_flag'];
$filter_flag=(empty($_POST['filter_flag'])) ? '' : $_POST['filter_flag'];
$filt_val=(empty($_POST['filt_val'])) ? '' : $_POST['filt_val'];
$cutoffType=(empty($_POST['cutoffType'])) ? '' : $_POST['cutoffType'];
$pct_cutoff=(empty($_POST['pct_cutoff'])) ? '' : $_POST['pct_cutoff'];
$blst_cutoff=(empty($_POST['blst_cutoff'])) ? '' : $_POST['blst_cutoff'];
$searchType=(empty($_POST['searchType'])) ? '' : $_POST['searchType'];
$program = (empty($_POST['program'])) ? '' : $_POST['program'];
//$dot = (empty($_GET['dot'])) ? 0 : $_GET['dot'];
$querySeq=(empty($_POST['querySeq'])) ? '' : $_POST['querySeq'];
$blastagainstfile=(empty($_FILES['blastagainstfile']['name'])) ? '' : $_FILES['blastagainstfile']['name'];
$alignmentView = (empty($_GET['alignmentView'])) ? '' : $_GET['alignmentView'];

if ($blast_flag == 1) {
	$jobid = time().rand(10, 99);
}
if (!$blast_flag && !$jobid) {
	echo "<p>Error: No job submitted.</p>";	
	footer();
	exit;
}

if ($searchType == 'advanced') {
	$expect=(empty($_POST['expect'])) ? 10 : $_POST['expect'];
	$wordSize = (empty($_POST['wordSize'])) ? '' : $_POST['wordSize'];
	$targetSeqs = (empty($_POST['targetSeqs'])) ? '' : $_POST['targetSeqs'];
	$mmScore = (empty($_POST['mmScore'])) ? '' : $_POST['mmScore'];
	$matrix = (empty($_POST['matrix'])) ? '' : $_POST['matrix'];
	$gapCost = (empty($_POST['gapCost'])) ? '' : $_POST['gapCost'];	
	$filter = (empty($_POST['filter'])) ? 'F' : $_POST['filter'];
	$softMask = (empty($_POST['softMask'])) ? 'F' : $_POST['softMask'];
	$lowerCaseMask = (empty($_POST['lowerCaseMask'])) ? 'F' : $_POST['lowerCaseMask'];
	$ungapAlign = (empty($_POST['ungapAlign'])) ? 'F' : $_POST['ungapAlign'];
	$alignmentView = (empty($_POST['outFmt'])) ? 0 : $_POST['outFmt'];	
	$geneticCode = (empty($_POST['qCode'])) ? '' : $_POST['qCode'];
	$dbGeneticCode = (empty($_POST['dbCode'])) ? '' : $_POST['dbCode'];
	$otherParam = (empty($_POST['OTHER_ADVANCED'])) ? '' : $_POST['OTHER_ADVANCED'];	
	if ($otherParam) {
		if (!preg_match("/^\s+$/", $otherParam) && !preg_match("/^\s*\-[A-Za-z]/", $otherParam)) {
			echo "Error: The other advanced options must start with \"-\"";
			exit;
		}
	}	
	$advanceParam = "$expect!#%$wordSize!#%$targetSeqs!#%$mmScore!#%$matrix!#%$gapCost!#%$filter!#%$softMask!#%$lowerCaseMask!#%$ungapAlign!#%$alignmentView!#%$geneticCode!#%$dbGeneticCode!#%$otherParam";
/*	echo "expect: $expect<br>";
	echo "wordSize: $wordSize<br>";
	echo "targetSeqs: $targetSeqs<br>";
	echo "mmScore: $mmScore<br>";
	echo "matrix: $matrix<br>";
	echo "gapCost: $gapCost<br>";
	echo "filter: $filter<br>";
	echo "softMask: $softMask<br>";
	echo "lowerCaseMask: $lowerCaseMask<br>";
	echo "ungapAlign: $ungapAlign<br>";
	echo "alignmentView: $alignmentView<br>";
	echo "geneticCode: $geneticCode<br>";
	echo "dbGeneticCode: $dbGeneticCode<br>";
	echo "otherParam: $otherParam<br>";*/
}else {
	$advanceParam = "";
}

if (!$alignmentView) {
	$alignmentView = 0;
}

$uploadDir = "$outputDir/$jobid";
if($blast_flag == 1) {
	$nlstr = chr(10);
	$crstr = chr(13);
	
	mkdir($uploadDir);
	chmod($uploadDir, 0755);
	if($_FILES['queryfile']['name']) {
		$uploadfile = "$uploadDir/$jobid.blastinput.txt";	
		if (move_uploaded_file($_FILES['queryfile']['tmp_name'], $uploadfile)) {
		
		}else {
			echo "blastinput.txt<br>";
			print "Possible file upload attack!  Here's some debugging info:\n";
			print_r($_FILES);
			exit;
		}
		
		@ $fp = fopen($uploadfile, "r");
		if(!$fp) {
			echo "<p><strong> error: couldn't open uploadfile </strong></p></body></html>";
			exit;
		}
		$buffer = fread($fp, filesize($uploadfile));
		fclose($fp);
	
		if(!preg_match("/>/", $buffer)) {
			echo "<p style='color: red'>Error: The uploading sequence file is not in fasta format. Please format your sequence file and upload again.</p><br>";
			exit;
		}else{
			if(preg_match("/\r\n/", $buffer)) {
				$buffer_mod = str_replace("\r", "", $buffer);
				$buffer = $buffer_mod;			
			}else if (preg_match("/\r/", $buffer)) {
				$buffer_mod = str_replace("\r", "\n", $buffer);
				$buffer = $buffer_mod;			
			}
				
			$i = 0;
			while(substr($buffer, $i, 1) != ">") {
				$i++;
			}
			$buffer = substr($buffer, $i);
			
			@ $fp = fopen($uploadfile, "w", 1);
			if(!$fp) {
				echo "<p><strong> error: $php_errormsg </strong></p></body></html>";
				exit;
			}
			fwrite($fp, $buffer);
			fclose($fp);
		}		
	}elseif($querySeq && !preg_match("/^\s+$/", $querySeq)) {
		$fp1=fopen("$uploadDir/$jobid.blastinput.txt", "w",1);
		if (!$fp1)
		{
			echo "<p><strong> error: couldn't open $uploadDir/$jobid.blastinput.txt </strong></p></body></html>";
			exit;
		}
		
		fwrite($fp1, $querySeq);
		fclose($fp1);
	}else {
		echo "<p style='color: red'>Error: please enter your query sequence or upload your fasta sequence file.</p><br>";
		exit;
	}
	
	if(!$_FILES['blastagainstfile']['name'] && !$patientIDarray[0]) {
		echo "<p style='color: red'>Error: please choose database(s) or upload your fasta sequence file to blast against.</p><br>";
		exit;
	}
	
	if($_FILES['blastagainstfile']['name']) {
		$uploadfile = "$uploadDir/$jobid.blastagainst.txt";
	
		if (move_uploaded_file($_FILES['blastagainstfile']['tmp_name'], $uploadfile)) {
		}else {
			echo "blastagainst.txt<br>";
		   print "Possible file upload attack!  Here's some debugging info:\n";
		   print_r($_FILES);
		   exit;
		}
	
		@ $fp = fopen($uploadfile, "r");
		if(!$fp) {
			echo "<p><strong> error: $php_errormsg </strong></p></body></html>";
			exit;
		}
		$buffer = fread($fp, filesize($uploadfile));
		fclose($fp);
		
		if(!preg_match("/>/", $buffer)) {
			echo "<p style='color: red'>Error: The uploading sequence file to blast against is not in fasta format. Please format your sequence file and upload again.</p>";
			footer();
			exit;
		}else{
			/* make upload file nexus format */
			if(preg_match("/\r\n/", $buffer)) {
				$buffer_mod = str_replace("\r", "", $buffer);
				$buffer = $buffer_mod;			
			}else if (preg_match("/\r/", $buffer)) {
				$buffer_mod = str_replace("\r", "\n", $buffer);
				$buffer = $buffer_mod;			
			}
			/* clean sequences to eliminate makeblastdb error for blast+ application */
			$buffer_array = preg_split("/\n/", $buffer);
			
			@ $fp = fopen($uploadfile, "w", 1);
			if(!$fp) {
				echo "<p><strong> error: $php_errormsg </strong></p></body></html>";
				exit;
			}
			foreach ($buffer_array as $line) {
				if (!preg_match("/^\s*$/", $line)) {
					if (preg_match("/>/", $line)) {
						$line .= "\n";
						fwrite($fp, $line);
					}else {
						if (!preg_match("/^\-+$/", $line)) {
							if (preg_match("/\-/", $line)) {
								$line = str_replace("-", "", $line);
							}
							$line .= "\n";
							fwrite($fp, $line);
						}
					}
				}				
			}
			fclose($fp);
		}		
	}
}

if (!file_exists("$uploadDir/$jobid.blastinput.txt")) {
	echo "<p>Couldn't find the job. Either the job has expired or the job id was wrong.</p>";
	footer();
	exit;
}

if($opt == 'wait') {
	$email=(empty($_GET['email'])) ? '' : $_GET['email'];
}else {
	$email=(empty($_POST['email'])) ? '' : $_POST['email'];
}

if($cutoffType == 'pct') {
	$criterion = $pct_cutoff;
}
if($cutoffType == 'blst') {
	$criterion = $blst_cutoff;
}

if(!$opt || $opt == 'wait') {	
	echo "<h3>Your job #$jobid is being processed. </h3>";	
	echo "<div class=\"spinner\">";
		#print "<div class=\"circle\"></div>";
	echo "</div>";		
	if($email) {
		echo "<p>Result will be send to <b>$email</b> when the job finishes.</p>";
		echo "<p>You can close browser if you want.</p>";
	}else {
		echo "<p>Please wait here to watch the progress of your job.</p>";
		echo "<p>This page will update itself automatically until search is done.</p>";	
	}
}

if(!$opt || $opt == 'wait') {
	echo "<script>";
		echo "function autoRefresh() {";
		echo "location.href = \"blastresult.php?jobid=$jobid&alignmentView=$alignmentView&email=$email&opt=wait\";";
		echo "}";
		echo "setInterval('autoRefresh()', 10000);";
	echo "</script>";
}

if($blast_flag == 1) {
	$serverName = $_SERVER['SERVER_NAME'];
	$http_referer = $_SERVER['HTTP_REFERER'];
	if (isset($_SERVER['HTTP_X_REAL_IP'])) {
		$remote_addr = $_SERVER['HTTP_X_REAL_IP'];
	}else {
		$remote_addr = $_SERVER['REMOTE_ADDR'];
	}
	$blastagainst = "Normal";
	$i = 1;
	/*get seqs from database to blast against and format the result into fasta fromat in a file*/
	if($blastagainstfile) {
		$blastagainst = "$uploadDir/$jobid.blastagainst.txt";
		$blastagainststring = "You have selected to blast against your own sequences in file $blastagainstfile";
		$source = "File: $blastagainstfile";
		$i = 0;
	}elseif($patientIDarray[0] == 'Genbank') {
		$blastagainst = "$dbPath/nucleotide/viral/gbvrl.fas";
		$blastagainststring = 'You have selected to blast against viral subset of Genbank';
		$source = "Viral GenBank";
	}elseif($patientIDarray[0] == 'Viroverse') {
		$blastagainst = "$dbPath/nucleotide/viroverse/viroverse.fas";
		$blastagainststring = 'You have selected to blast against Viroverse sequence database';
		$source = "Viroverse";
	}elseif($patientIDarray[0] == 'hiv') {
		$blastagainst = "$dbPath/nucleotide/hiv/gbhiv.fas";
		$blastagainststring = 'You have selected to blast against HIV genbank';
		$source = "HIV GenBank";
	}elseif($patientIDarray[0] == 'LanlCompletegenome') {
		$blastagainst = "$dbPath/nucleotide/download_lanl/hiv_complete_genome/HIV1_FLT_2017_genome_DNA.fasta";
		$blastagainststring = 'You have selected to blast against LANL HIV-1 complete genome sequences';
		$source = "HIV-1 complete genome";
	}elseif($patientIDarray[0] == 'Lanlsubtyperef') {
		$blastagainst = "$dbPath/nucleotide/download_lanl/hiv_subtype_ref/HIV1_REF_2010_genome_DNA.fasta";
		$blastagainststring = 'You have selected to blast against LANL HIV-1 subtype reference sequences';
		$source = "HIV-1 subtype reference";
	}elseif($patientIDarray[0] == 'LanlConsensus') {
		$blastagainst = "$dbPath/nucleotide/download_lanl/hiv_consensus/HIV1_CON_2002_genome_DNA.fasta";
		$blastagainststring = 'You have selected to blast against LANL HIV-1 consensus sequences';
		$source = "HIV-1 consensus";
	}elseif($patientIDarray[0] == 'UniVec') {
		$blastagainst = "$dbPath/nucleotide/univec/vector";
		$blastagainststring = 'You have selected to blast against NCBI vector database';
		$source = "Vector";
	}elseif ($patientIDarray[0] == 'HIVProt') {
		$blastagainst = "$dbPath/protein/hiv/hiv_prot.fas";
		$blastagainststring = 'You have selected to blast against HIV proteins';
		$source = "HIV protein";
	}elseif ($patientIDarray[0] == 'ViroProt') {
		$blastagainst = "$dbPath/protein/viral/vrl_prot.fas";
		$blastagainststring = 'You have selected to blast against Viral proteins';
		$source = "Viral protein";
	}
		
	while(array_key_exists($i, $patientIDarray)) {
		if($patientIDarray[$i] == 'Genbank') {
			$blastagainst .= " $dbPath/nucleotide/viral/gbvrl.fas";
			$blastagainststring .= ', viral subset of Genbank';
			$source .= ",Viral GenBank";
		}elseif($patientIDarray[$i] == 'Viroverse') {
			$blastagainst .= " $dbPath/nucleotide/viroverse/viroverse.fas";
			$blastagainststring .= ', Viroverse sequence database';
			$source .= ",Viroverse";
		}elseif($patientIDarray[$i] == 'hiv') {
			$blastagainst .= " $dbPath/nucleotide/hiv/gbhiv.fas";
			$blastagainststring .= ', HIV genbank';
			$source .= ",HIV GenBank";
		}elseif($patientIDarray[$i] == 'LanlCompletegenome') {
			$blastagainst .= " $dbPath/nucleotide/download_lanl/hiv_complete_genome/HIV1_FLT_2017_genome_DNA.fasta";
			$blastagainststring .= ', LANL HIV-1 complete genome sequences';
			$source .= ",HIV-1 complete genome";
		}elseif($patientIDarray[$i] == 'Lanlsubtyperef') {
			$blastagainst .= " $dbPath/nucleotide/download_lanl/hiv_subtype_ref/HIV1_REF_2010_genome_DNA.fasta";
			$blastagainststring .= ', LANL HIV-1 subtype reference sequences';
			$source .= ",HIV-1 subtype reference";
		}elseif($patientIDarray[$i] == 'LanlConsensus') {
			$blastagainst .= " $dbPath/nucleotide/download_lanl/hiv_consensus/HIV1_CON_2002_genome_DNA.fasta";
			$blastagainststring .= ', LANL HIV-1 consensus sequences';
			$source .= ",HIV-1 consensus";
		}elseif($patientIDarray[$i] == 'UniVec') {
			$blastagainst .= " $dbPath/nucleotide/univec/vector";
			$blastagainststring .= ', NCBI UniVec database';
			$source .= ",Vector";
		}elseif ($patientIDarray[$i] == 'HIVProt') {
			$blastagainst .= " $dbPath/protein/hiv/hiv_prot.fas";
			$blastagainststring .= ', HIV proteins';
			$source .= ",HIV protein";
		}elseif ($patientIDarray[$i] == 'ViroProt') {
			$blastagainst .= " $dbPath/protein/viral/vrl_prot.fas";
			$blastagainststring .= ', Viral proteins';
			$source .= ",Viral protein";
		}
		$i++;
	}
	
	$params = "$jobid!#%$uploadDir!#%$blastagainst!#%$program!#%$blastagainststring!#%$source!#%$remote_addr!#%$email!#%$searchType";
	if ($advanceParam) {
		$params .= '!#%'.$advanceParam;
	}
	/*create child process to run perl script which do the blast search and write output data to apropriate files*/
	/* only can be used in unix like server */
	//system("perl blast.pl \"$basicParam\" \"$advanceParam\" >/dev/null &");
	
	/* good way that can be used in both unix and windows */
	pclose(popen("perl blast.pl \"$params\" &", "r"));
}

/* error log if there is error in BLAST */
$errFile = "$uploadDir/$jobid.err";
/* parent process continue here to check child process done or not */
$filename = "$uploadDir/$jobid.blaststring.txt";
if (file_exists($errFile) && filesize($errFile) > 0) {
	if(!$opt || $opt == 'wait') {
		echo "<script LANGUAGE=JavaScript>";
		echo "location.replace('blastresult.php?jobid=$jobid&opt=none')";
		echo "</script>";
	}else {
		echo "<p>There is error in executing NCBI BLAST. Following is the error message:<p>";
		$fperr = fopen("$uploadDir/$jobid.err", "r");
		if(!$fperr) {
			echo "<p><strong> $jobid.err error: $errors  </strong></p></body></html>";
			exit;
		}
	
		while (!feof($fperr))
		{
			$line = rtrim(fgets($fperr)); 
			echo "$line<br>";
		}
		fclose($fperr);
	}	
}elseif(file_exists($filename)) {
	$link = "$htdocsRoot/$dataPath/$jobid";
/*	if (!file_exists($link)) {
		symlink($uploadDir, $link);
	}	*/
	if ($alignmentView) {
		echo "<script LANGUAGE=JavaScript>";
		echo "location.replace('/$dataPath/$jobid/$jobid.blast')";
		echo "</script>";
	}else {
		if($blast_flag == 'Parse again') {
			$print_flag = 0;
			$cutoff_count = 0;
		
			@ $fpout=fopen("$uploadDir/$jobid.par", "r");
			if (!$fpout)
			{
				echo "<p><strong> $uploadDir/$jobid.par error: $phperrormsg $errors </strong></p></body></html>";
				exit;
			}
			
			@ $fpout3 = fopen("$uploadDir/$jobid.out.par", "w", 1);
			if(!$fpout3) {
				echo "<p><strong> $uploadDir/$jobid.out.par error: $errors $phperrormsg </strong></p></body></html>";
				exit;
			}
		
			while (!feof($fpout))
			{
				$fpout2_str = '';
				$line = rtrim(fgets($fpout)); 
				if (!$line) {
						continue;
					}
				list($page, $query_name, $match_name, $gi, $seq_source, $score, $identities, $percentage, $e_value, $link) = preg_split("/\t/", $line);
				
				if($cutoffType == 'pct') {
					$subject = $percentage;
				}else {
					$subject = $score;
				}
	
				if($subject >= $criterion) {
					fwrite($fpout3, "$page	$query_name	$match_name	$gi	$seq_source	$score	$identities	$percentage	$e_value	$link\n");
					$cutoff_count++;
				}
			}
	
			fclose ($fpout);
			fclose($fpout3);
			
			@ $fp = fopen("$uploadDir/$jobid.blastcount.txt", "w", 1);
			if(!$fp) {
				echo "<p><strong> error: $php_errormsg  </strong></p></body></html>";
				exit;
			}else {
				fwrite($fp, "$cutoff_count\n");
			}
			
			fclose($fp);
		}
		
		$filename = "$uploadDir/$jobid.blastcount.txt";
		
//		while(!file_exists($filename)) {}
		
		if(!$opt || $opt == 'wait') {
			echo "<script LANGUAGE=JavaScript>";
			echo "location.replace('blastresult.php?jobid=$jobid&opt=none')";
			echo "</script>";
		}else {
			@ $fp = fopen("$uploadDir/$jobid.blastcount.txt", "r");
			if(!$fp) {
				echo "<p><strong> error: $php_errormsg  </strong></p></body></html>";
				exit;
			}
			
			if(!feof($fp)) {
				$cutoff_count = fgets($fp);
			}
			fclose($fp);
		
			@ $fp = fopen("$uploadDir/$jobid.blaststring.txt", "r");
			if(!$fp) {
				echo "<p><strong> error: $php_errormsg  </strong></p></body></html>";
				exit;
			}
			
			if(!feof($fp)) {
				$blastagainststring = rtrim(fgets($fp));
			}
			fclose($fp);
			
			if($cutoff_count == 0) {
				echo "<p>$blastagainststring (No significant similarity found. Please increase expect value to blast again)";
				echo "<p><a href=viroblast.php>Back to ViroBLAST</a></p>";
			}else {
				echo "<p>$blastagainststring ($cutoff_count significant similarities)";
				echo "<p><a href=/$dataPath/$jobid/$jobid.blast1.html target='_blank'>Inspect ViroBLAST output</a>";
				echo "<p><a href=download.php?jobid=$jobid&ID=$jobid".".tblout.txt>Download tab delimited tabular output</a>";
				echo "<form action='blastresult.php?jobid=$jobid&opt=$opt' method='post'>";	
				
				echo "<p>Re-parse current blast results (please select cutoff criterion):<br>";
				echo "<table style='font-size: 12px'>";
				echo "<tr><td><input type='radio' checked name='cutoffType' value='pct'>Similarity percentage</td><td></td>";
				echo "<td>Cutoff %: </td><td><input type='text' name='pct_cutoff' value=95 size=6 maxlength=6></td></tr>";
				
				echo "<tr><td><input type='radio' name='cutoffType' value='blst'>Blast score</td><td></td>";		
				echo "<td>Cutoff score: </td><td><input type='text' name='blst_cutoff' value=1000 size=6 maxlength=6></td>";
				echo "<td><input type='submit' name='blast_flag' value='Parse again'>";
				echo "</td></tr></table></p>";
				
				echo "<p>Filter current page by score:";
				echo "<p>Show <select name='filt_val'>";
				echo "<option value='0' selected>- All -";
				echo "<option value='1'>Top score";
				echo "<option value='5'>Top 5 scores";
				echo "<option value='10'>Top 10 scores";
				echo "</select> for each query sequence <input type='submit' name='filter_flag' value='Filter'></font></p>";
								
				echo "</form>";
		
				echo "<form action='sequence.php?jobid=$jobid' method='post' target='_blank' onsubmit=\"return checkform(this);\">";
				echo "<p>Retrieve and download subject sequences in FASTA format:  ";
				echo "<input type='radio' checked name='seqtype' value='entire'>Entire sequence ";
				echo "<input type='radio' name='seqtype' value='mapping'>Region mapped to query<br>";			
				echo "<input type='checkbox' name='dldseq' value='all'>  Check here to download All sequences... ";
				echo "OR select particular sequences of interest below";	
				echo "<p><input type='submit' value='Submit'> your selection of sequences to download<br><br>";	
				echo "<div>";
				echo "<table width=100% border = 1 style='font-size:10px' class='sortable'>";
				echo "<thead><tr><th>Query</th><th>Subject</th><th>Subject source</th><th>Score</th><th>Identities (Query length)</th><th>Percentage</th><th>Expect</th></tr></thead>";
				echo "<tbody>";
				@ $fp = fopen("$uploadDir/$jobid.download.txt", "w", 1) or die("Cannot open file: $jobid.download.txt");
				@ $tblfp = fopen("$uploadDir/$jobid.tblout.txt", "w", 1) or die("Cannot open file: $jobid.tblout.txt");
				fwrite($tblfp, "Query\tSubject\tSubject source\tScore\tIdentities (Query length)\tPercentage\tExpect\n");
				if($blast_flag == 'Parse again' || ($opt == 'none' && !$filter_flag)) {
					@ $fpout3=fopen("$uploadDir/$jobid.out.par", "r");
					if(!$fpout3) {
						echo "<p><strong> error: $php_errormsg  </strong></p></body></html>";
						exit;
					}
					$i = 0;
					$queryName = $preQueryName = "";
					while(!feof($fpout3)) {
						$row = fgets($fpout3);
						if (!$row) {
							continue;
						}
						$element = preg_split("/\t/", $row);		
						$page = $element[0];
						$queryName = $element[1];
						$target_name = $element[9];
						$var_target = $page."\t".$element[1]."\t".$element[2]."\t".$element[3]."\t".$element[4];
						if(count($element) != 1) {
							if($queryName == $preQueryName) {
								$i++;
							}else {
								$i = 0;
							}
							
							if($i < 10) {							
								echo "<tr align='center'><td>$element[1]</td><td align=left><input type='checkbox' id='checkedSeq' name='target[]' value=\"$var_target\">$target_name</td><td>$element[4]</td><td><a href=/$dataPath/$jobid/$jobid.blast$page.html#$element[1]$element[2] target='_blank'>$element[5]</a></td><td>$element[6]</td><td>$element[7]</td><td>$element[8]</td></tr>";
								fwrite($fp, "$var_target\n");
								fwrite($tblfp, "$element[1]\t$element[2]\t$element[4]\t$element[5]\t$element[6]\t$element[7]\t$element[8]\n");
							}					
						}
						$preQueryName = $queryName;
					}
					fclose($fpout3);
				}
				
				if($filter_flag == 'Filter')
				{
					@ $fpout3=fopen("$uploadDir/$jobid.out.par", "r");
					if(!$fpout3) {
						echo "<p><strong> error: $php_errormsg  </strong></p></body></html>";
						exit;
					}
					$i = 0;
					while(!feof($fpout3)) {
						$row = fgets($fpout3);
						if (!$row) {
							continue;
						}
						$element = preg_split("/\t/", $row);
						$page = $element[0];
						$target_name = $element[9];
						$var_target = $page."\t".$element[1]."\t".$element[2]."\t".$element[3]."\t".$element[4];
						if(count($element) != 1) {
							if($filt_val != 0) {
								if($i == 0) {
									$query_name = $element[1];
									echo "<tr align='center'><td>$element[1]</td><td align=left><input type='checkbox' id='checkedSeq' name='target[]' value=\"$var_target\">$target_name</td><td>$element[4]</td><td><a href=/$dataPath/$jobid/$jobid.blast$page.html#$element[1]$element[2] target='_blank'>$element[5]</a></td><td>$element[6]</td><td>$element[7]</td><td>$element[8]</td></tr>";
									fwrite($fp, "$var_target\n");
									fwrite($tblfp, "$element[1]\t$element[2]\t$element[4]\t$element[5]\t$element[6]\t$element[7]\t$element[8]\n");
									$i++;
								}elseif($query_name == $element[1] && $i < $filt_val) {
									echo "<tr align='center'><td>$element[1]</td><td align=left><input type='checkbox' id='checkedSeq' name='target[]' value=\"$var_target\">$target_name</td><td>$element[4]</td><td><a href=/$dataPath/$jobid/$jobid.blast$page.html#$element[1]$element[2] target='_blank'>$element[5]</a></td><td>$element[6]</td><td>$element[7]</td><td>$element[8]</td></tr>";
									fwrite($fp, "$var_target\n");
									fwrite($tblfp, "$element[1]\t$element[2]\t$element[4]\t$element[5]\t$element[6]\t$element[7]\t$element[8]\n");
									$i++;
								}elseif($query_name != $element[1]) {
									echo "<tr align='center'><td>$element[1]</td><td align=left><input type='checkbox' id='checkedSeq' name='target[]' value=\"$var_target\">$target_name</td><td>$element[4]</td><td><a href=/$dataPath/$jobid/$jobid.blast$page.html#$element[1]$element[2] target='_blank'>$element[5]</a></td><td>$element[6]</td><td>$element[7]</td><td>$element[8]</td></tr>";
									$query_name = $element[1];
									fwrite($fp, "$var_target\n");
									fwrite($tblfp, "$element[1]\t$element[2]\t$element[4]\t$element[5]\t$element[6]\t$element[7]\t$element[8]\n");
									$i=1;
								}
							}else {
								echo "<tr align='center'><td>$element[1]</td><td align=left><input type='checkbox' id='checkedSeq' name='target[]' value=\"$var_target\">$target_name</td><td>$element[4]</td><td><a href=/$dataPath/$jobid/$jobid.blast$page.html#$element[1]$element[2] target='_blank'>$element[5]</a></td><td>$element[6]</td><td>$element[7]</td><td>$element[8]</td></tr>";
								fwrite($fp, "$var_target\n");
								fwrite($tblfp, "$element[1]\t$element[2]\t$element[4]\t$element[5]\t$element[6]\t$element[7]\t$element[8]\n");
							}							
						}
					}
					fclose($fpout3);
				}
				fclose($fp);
				fclose($tblfp);
				echo "</tbody></table></div></form>";
				echo "<p><a href=\"#top\">Top</a>";		
			}
		}
	}
}

footer();

function footer() {
	echo "</div>";
	echo "<div id='footer' align='center'>";
	echo "<p class='copyright'>&copy; 2025 Fred Hutch Cancer Center. All rights reserved.</p>";
	echo "</div>";
	echo "</body>";
	echo "</html>";
}	

?>
