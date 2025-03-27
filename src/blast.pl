#!/usr/bin/perl -w

###########################################################################################################################
# This script runs a child process of viroblast including NCBI's blast+, parse and write to files, and send email to user #
# Author: Wenjie Deng                                                                                                     #
# Modifications: inplemented NCBI's BLAST+ program                                                                        #
# Date: 2010-06-24                                                                                                        #
###########################################################################################################################

use strict;
use Email::Sender::Simple qw(sendmail);
use Email::Sender::Transport::SMTP qw();
use Email::Simple;
use Email::Simple::Creator; # For creating the email

my $startTime = time();
my ($expect, $wordSize, $targetSeqs, $mmScore, $matrix, $gapCost, $filter, $softMask, $lowerCaseMask, $ungapAlign, $outfmt, $geneticCode, $dbGeneticCode, $otherParam);
my $param = $ARGV[0];
my @params = split /!#%/, $param;
my $jobid = shift @params;
my $uploadDir = shift @params;
my $blastagainst = shift @params;
my $program = shift @params;
my $blastagainststring = shift @params;
my $source = shift @params;
my $ip = shift @params;
my $email = shift @params;
my $searchType = shift @params;
if ($searchType eq 'advanced') {
	$expect = shift @params;
	$wordSize = shift @params;
	$targetSeqs = shift @params;
	$mmScore = shift @params;
	$matrix = shift @params;
	$gapCost = shift @params;
	$filter = shift @params;
	$softMask = shift @params;
	$lowerCaseMask = shift @params;
	$ungapAlign = shift @params;
	$outfmt = shift @params;
	$geneticCode = shift @params;
	$dbGeneticCode = shift @params;
	$otherParam = shift @params;
}
my $dbPath = $ENV{'VIROBLAST_DB_PATH'};
my %sourcefile = (
	"HIV GenBank"             => "$dbPath/nucleotide/hiv/gbhiv.acc",
	"Viral GenBank"           => "$dbPath/nucleotide/viral/gbvrl.acc",
	"HIV-1 complete genome"   => "$dbPath/nucleotide/download_lanl/hiv_complete_genome/HIV1_FLT_2017_genome_DNA.acc",
	"HIV-1 subtype reference" => "$dbPath/nucleotide/download_lanl/hiv_subtype_ref/HIV1_REF_2010_genome_DNA.acc",
	"HIV-1 consensus"         => "$dbPath/nucleotide/download_lanl/hiv_consensus/HIV1_CON_2002_genome_DNA.acc",
	"Vector"                  => "$dbPath/nucleotide/univec/vector.acc",
	"HIV protein"             => "$dbPath/protein/hiv/hiv_prot.acc",
	"Viral protein"           => "$dbPath/protein/viral/vrl_prot.acc",
);

open(LOG, ">$uploadDir/$jobid.log");
print LOG "Param: ", $param, "\n";
my @sources = split /\,/, $source;
my %sourceHash;
foreach (@sources) {
	$sourceHash{$_} = 1;
}
my $format = 0;
# viroblast default setting for max target sequences is 50
my $num_descriptions = my $num_alignments = my $max_target_seqs = 50;

print LOG "Job Id: ", $jobid, "\n";
print LOG "Search type: $searchType\n";
print LOG "Blast against: ", $blastagainst, "\n";
print LOG "Program: ", $program, "\n";
print LOG "email: ", $email, "\n";
print LOG "Source: ", $source, "\n";
print LOG "upload dir: $uploadDir\n";

my @cmd = ();
my $command = "";
if ($program eq 'megablast') {
	push @cmd, 'blastn';
	$command = "blastn";
}else {
	push @cmd, $program;
	$command = "$program";
}
unless ($program eq 'tblastx') {
	$command .= " -task $program";
	push @cmd, '-task', $program;
}
push @cmd, '-db', $blastagainst, '-query', "$uploadDir/$jobid.blastinput.txt", '-out', "$uploadDir/$jobid.out";
$command .= " -db \"$blastagainst\" -query $uploadDir/$jobid.blastinput.txt -out $uploadDir/$jobid.out";
if ($searchType eq "basic") {
	push @cmd, '-html';
	$command .= " -html";
}else {
	$num_descriptions = $num_alignments = $max_target_seqs = $targetSeqs;
	$format = $outfmt;
	unless ($format) {
		push @cmd, '-html';
		$command .= " -html";
	}
	push @cmd, '-evalue', $expect, '-word_size', $wordSize, '-outfmt', $format;
	$command .= " -evalue $expect -word_size $wordSize -outfmt $format";
	print LOG "Expect: $expect\n";
	print LOG "Word size: $wordSize\n";
	print LOG "Max target sequences: $targetSeqs\n";	
	if ($mmScore) {
		my ($reward, $penalty) = split /,/, $mmScore;
		push @cmd, '-reward', $reward, '-penalty', $penalty;
		$command .= " -reward $reward -penalty $penalty";	
		print LOG "Nucleotide match reward: $reward\n";
		print LOG "Nucleotide mismatch penalty: $penalty\n";
	}
	if ($matrix) {
		push @cmd, '-matrix', $matrix;
		$command .= " -matrix $matrix";
		print LOG "Matrix: $matrix\n";
	}
	if ($gapCost && $gapCost =~ /Existence: (\d+), Extension: (\d+)/) {	# tblastx no gap costs options
		my $gapOpen = $1;
		my $gapExtend = $2;
		push @cmd, '-gapopen', $gapOpen, '-gapextend', $gapExtend;
		$command .= " -gapopen $gapOpen -gapextend $gapExtend";
		print LOG "Gap open cost: $gapOpen\n";
		print LOG "Gap extend cost: $gapExtend\n";
	}
	print LOG "Filter low complexity regions: $filter\n";
	print LOG "Mask for lookup table only: $softMask\n";
	print LOG "Mask for lower case letters: $lowerCaseMask\n";
	print LOG "Perform ungapped alignment: $ungapAlign\n";
	print LOG "BLAST output format: $format\n";
	if ($filter eq 'F') {
		if ($program eq 'blastn' or $program eq 'megablast') {	# for blastn, default filter is "-dust yes"
			push @cmd, '-dust', 'no';
			$command .= " -dust no";
		}else {	# else except blastp, default filter is "-seg yes";
			unless ($program eq 'blastp') {
				push @cmd, '-seg', 'no';
				$command .= " -seg no";
			}			
		}
	}else {
		if ($program eq 'blastp') {	# for blastp, default filter is "-seg no"
			push @cmd, '-seg', 'yes';
			$command .= " -seg yes";
		}
	}
	if ($softMask eq 'F') {
		if ($program eq 'blastn' or $program eq 'megablast') {	# the default value of soft masking for blastn is true
			push @cmd, '-soft_masking', 'false';
			$command .= " -soft_masking false";
		}
	}else {
		unless ($program eq 'blastn' and $program eq 'megablast') {	# the default value of soft masking other than blastn is false
			push @cmd, '-soft_masking', 'true';
			$command .= " -soft_masking true";
		}
	}
	if ($lowerCaseMask eq 'L') {	# the default value of lower case masking for all programs is false
		push @cmd, '-lcase_masking';
		$command .= " -lcase_masking";
	}
	if ($ungapAlign eq 'T') {	# default is gapped alignment
		push @cmd, '-ungapped';
		$command .= " -ungapped";
	}
	if ($geneticCode) {
		push @cmd, '-query_gencode', $geneticCode;
		$command .= " -query_gencode $geneticCode";
		print LOG "Query genetic code: $geneticCode\n";
	}
	if ($dbGeneticCode) {
		push @cmd, '-query_gencode', $dbGeneticCode;
		$command .= " -db_gencode $dbGeneticCode";
		print LOG "Database genetic code: $dbGeneticCode\n";
	}
	############# here ###############
	if ($otherParam) {
		my @otherArgs = split /\s+/, $otherParam;
		push @cmd, @otherArgs;
		$command .= " $otherParam";
		print LOG "Other parameters: $otherParam\n";
	}
}
if ($format < 5) {
	push @cmd, '-num_descriptions', $num_descriptions, '-num_alignments', $num_alignments;
	$command .= " -num_descriptions $num_descriptions -num_alignments $num_alignments 2>$uploadDir/$jobid.err";
}else {
	push @cmd, '-max_target_seqs', $max_target_seqs;
	$command .= " -max_target_seqs $max_target_seqs 2>$uploadDir/$jobid.err";
}
print LOG "Command: ", join(' ', @cmd);
print LOG "remote IP: $ip\n";
open STDERR, ">$uploadDir/$jobid.err";
close STDERR;
if($blastagainst =~ /blastagainst\.txt/) {
	my $rv = 0;	
	if ($program eq "blastp" || $program eq "blastx") {
		$rv = system("makeblastdb", '-in', "$uploadDir/$jobid.blastagainst.txt", '-logfile', '/tmp/formatdb.log', '-dbtype', 'prot');
	}else {
		$rv = system("makeblastdb", '-in', "$uploadDir/$jobid.blastagainst.txt", '-logfile', '/tmp/formatdb.log', '-dbtype', 'nucl');
	}
	unless ($rv == 0) {
		print LOG "makeblastdb failed: $rv\n";
		open ERR, ">>$uploadDir/$jobid.err" or die "couldn't open $jobid.err\n";
		print ERR "makeblastdb failed\n";
		close ERR;
		exit;
	}
}
# run blast program
my $rv = system (@cmd);
unless ($rv == 0) {
	print LOG "Program failed: $rv\n";
	exit;
}

my %nameSource;
if (scalar @sources > 1) {
	foreach my $source (@sources) {
		if ($source =~ /^File:/) {
			open IN, "$uploadDir/$jobid.blastagainst.txt" or die "couldn't open $jobid.blastagainst.txt: $!\n";
			while (my $line = <IN>) {
				chomp $line;
				if ($line =~ /^>(.*?)[,;\s+]/ || $line =~ /^>(\S+)/) {
					my $seqName = $1;
					push @{$nameSource{$seqName}}, $source;
				}
			}
			close IN;
		}else {
			my $infile = $sourcefile{$source};
			open IN, "<", $infile or die "couldn't open $infile: $!\n";
			while (my $line = <IN>) {
				chomp $line;
				next if $line =~ /^\s*$/;
				push @{$nameSource{$line}}, $source;
			}
			close IN;
		}
	}	
}

my $infile = "$uploadDir/$jobid.out";

if ($format) {	# out formats except for pairwise
	my %queryLenHash = ();
	my $queryLen = 0;
	if ($format == 9) {	# for hit table output, insert query sequence length to output file
		open INPUT, "$uploadDir/$jobid.blastinput.txt" or die "Couldn't open $jobid.blastinput.txt: $!\n";
		my $flag = 0;		
		my $query;
		while (my $line = <INPUT>) {
			chomp $line;
			next if ($line =~ /^\s*$/);
			$line =~ s/\s+$// if ($line =~ /\s+$/);
			if ($line =~ /^>(.*)$/) {
				if (!$flag) {
					$query = $1;
					$flag = 1;
				}else {
					$queryLenHash{$query} = $queryLen;
					$query = $1;
					$queryLen = 0;
				}				
			}else {
				my $len = length $line;
				for (my $i = 0; $i < $len; $i++) {
					if (substr ($line, $i, 1) =~ /[a-zA-Z]/) {
						$queryLen++;
					}
				}
			}
		}
		$queryLenHash{$query} = $queryLen;
		close INPUT;
	}
	open IN, $infile or die "Cannot open $infile: $!\n";
	open (OUT, ">$uploadDir/$jobid.blast") or die "Couldn't open $jobid.blast: $!\n";
	while (my $line = <IN>) {
		chomp $line;
		print OUT $line,"\n";
		if ($format == 9 && $line =~ /^\#\s+Query:\s+(.*)$/) {
			my $query = $1;
			print OUT "# Length: $queryLenHash{$query} letters\n";
		}
	}
	close IN;
	close OUT;
}else {	# pairwise out format
	my $size = (-s $infile);
	my $num_query = 0;
	my @query_array = ();
	open IN, $infile or die "Cannot open $infile: $!\n";
	while(my $line = <IN>) {		
		if($line =~ /<b>Query=<\/b>/) {
			$num_query++;			
		}
		if($line =~ /<b>Query=<\/b>\s*$/) {
			my $query_name = "Query".$num_query;
			push(@query_array, $query_name);
		}elsif($line =~ /<b>Query=<\/b>\s+(.*?)[,;\s+]/) {
			my $query_name = $1;
			push(@query_array, $query_name);				
		}
	}
	close IN;
	my $num_page = 1;
	my $size_per_page = 0;
	if($size > 6000000) {
		$num_page = int($size/5000000 + 1);
		$size_per_page = int($size/$num_page/100000)/10;
	}
	
	open(IN, $infile) || die "Cannot open in file\n";
	open(OUT1, ">$uploadDir/$jobid.blast1.html") || die "Cannot open out1 file\n";
	open(OUT2, ">$uploadDir/$jobid.out.par") || die "Cannot open par file\n";
	open(OUT3, ">$uploadDir/$jobid.par") || die "Cannot open par file\n";
	open(OUT4, ">$uploadDir/$jobid.blastcount.txt") || die "Cannot open count file\n";
#	chmod 0664, "$uploadDir/$jobid.out.par";
#	chmod 0664, "$uploadDir/$jobid.blastcount.txt";
	
	my $query_flag = 0;
	my $print_flag = 0;
	my $cutoff_count = 0;
	my $acc_query = 0;
	my $queryLen = 0;
	my $page = 1;
	my $open_flag = 1;
	my $tmp_file = "$uploadDir/$jobid.blast_tmp.html";
	my $index_file = "$uploadDir/$jobid.blast_index.txt";
	my $firstPRE = 1;
	my $start_query = my $end_query = 1;
	my $link = "";
	my $top_query = "";
	my ($query_name, $match_name, $name_anchor, $acc, $score, $e_value);
	while(my $line = <IN>) {
		if($line =~ /<b>Query=<\/b>/) {
			$acc_query++;
			if($open_flag == 1) {
				$start_query = $acc_query;
			}
			$query_flag = 1;
		}
		
		if ($query_flag && $line =~ /\Length=(\d+)/i) {
			$queryLen = $1;
			$query_flag = 0;
		}		
	
		if($line =~ /^\s*(.*)\s+\<a href\s*=\s*(#\S+)>\s*\S+<\/a>/) {
			$match_name = $1;
			$name_anchor = $2;
			$acc = '';
			if ($match_name =~ /^gi\|\d+\|\w+\|([A-Z]{1,5}\d+\.\d+)/ or $match_name =~ /^gi\|\d+\|\w+\|([A-Z]{2}_\d+\.\d+)/ or $match_name =~ /^([A-Z]{1,5}\d+\.\d+)/ or $match_name =~ /^([A-Z]{2}_\d+\.\d+)/ or $match_name =~ /^gnl\|\w+\|([A-Z]{1,5}\d+\.\d+)/) {	# blastn, tblastn, tblastx
				$acc = $1;
				$match_name =~ /^(.*?)[,;\s+]/;
				$match_name = $1;
				$match_name =~ s/\|/!#%/g;
				$line =~ s/\|/!#%/g;
				$line =~ s/$match_name/<a href=https:\/\/www.ncbi.nlm.nih.gov\/nuccore\/$acc?report=genbank target=_blank>$match_name<\/a>/;				
				$match_name =~ s/!#%/\|/g;
				$line =~ s/!#%/\|/g;
				$link = "<a href=https://www.ncbi.nlm.nih.gov/nuccore/$acc?report=genbank target=_blank>$match_name</a>";
			}elsif($match_name =~ /^(.*?)[,;\s+]/) {
				$match_name = $1;
			}
			$line =~ s/$name_anchor/#$query_name$match_name/;
		}elsif($line =~ /^\s*(.*?)<a (name\s*=\s*\S+)><\/a>\s*(.*?)$/) {
			$name_anchor = $2;
			$match_name = $3;
			if ($1 =~ /<script src=\"blastResult\.js\"><\/script>/) {
				$line =~ s/<script src=\"blastResult\.js\"><\/script>//;
			}
			$acc = '';
			if ($match_name =~ /^gi\|\d+\|\w+\|([A-Z]{1,5}\d+\.\d+)/ or $match_name =~ /^gi\|\d+\|\w+\|([A-Z]{2}_\d+\.\d+)/ or $match_name =~ /^([A-Z]{1,5}\d+\.\d+)/ or $match_name =~ /^([A-Z]{2}_\d+\.\d+)/ or $match_name =~ /^gnl\|\w+\|([A-Z]{1,5}\d+\.\d+)/) {	# blastn
				$acc = $1;
				if ($match_name =~ /^(.*?)[,;\s+]/ || $match_name =~ /^(\S+)/) {
					$match_name = $1;
				}
				$match_name =~ s/\|/!#%/g;
				$line =~ s/\|/!#%/g;
				$line =~ s/$match_name/$query_name on <a href=https:\/\/www.ncbi.nlm.nih.gov\/nuccore\/$acc?report=genbank target=_blank>$match_name<\/a>/;	
				$match_name =~ s/!#%/\|/g;
				$line =~ s/!#%/\|/g;
				$link = "<a href=https://www.ncbi.nlm.nih.gov/nuccore/$acc?report=genbank target=_blank>$match_name</a>";
			}elsif($match_name =~ /^(.*?)[,;\s+]/ || $match_name =~ /^(\S+)/) {
				$match_name = $1;
				$line =~ s/$match_name/$query_name on $match_name/;
				$link = $match_name;
			}
			$line =~ s/$name_anchor/name = $query_name$match_name/;			
			$print_flag = 1;
		}
		
		if($line =~ /<b>Query=<\/b>\s*$/) {
			$query_name = "Query".$acc_query;
			$line = "<a name = $query_name></a>".$line;
		}elsif($line =~ /<b>Query=<\/b>\s+(.*?)[,;\s+]/) {
			$query_name = $1;
			$line = "<a name = $query_name></a>".$line;
		}
		
		if($num_page > 1) {		
			if($open_flag == 1) {
				open(OUT, ">$tmp_file") || die "Cannot open out file: $tmp_file\n";
				print OUT $top_query;
				$open_flag = 0;
			}
			
			if($line =~ /<b>Query=<\/b>/ || $line =~ /<\/HTML>/i) {
				my $size_tmp_file = (-s $tmp_file)/1000000;
				if ($line =~ /<\/HTML>/i) {
					$end_query = $acc_query;
					print OUT $line;
					$open_flag = 1;
				}elsif($size_tmp_file >= $size_per_page) {					
					$end_query = $acc_query - 1;
					$open_flag = 1;
				}	
				
				if ($open_flag == 1) {	
					open(INDEX, ">$index_file") || die("Cannot open index file: $index_file\n");
					print INDEX "<HTML>\n";
					print INDEX "<TITLE>BLAST Search Results</TITLE>\n";
					print INDEX "<BODY BGCOLOR=\"#FFFFFF\" LINK=\"#0000FF\" VLINK=\"#660099\" ALINK=\"#660099\">\n";
					print INDEX "<PRE>\n";
					
					if($end_query == $num_query) {
						my $prev_page = $page - 1;
						print OUT "[<a href=$jobid.blast1.html>First</a>][<a href=$jobid.blast$prev_page.html>Previous</a>]  <b>Results of query sequence $start_query through $end_query</b>\n\n";
						print INDEX "[<a href=$jobid.blast1.html>First</a>][<a href=$jobid.blast$prev_page.html>Previous</a>]  <b>Results of query sequence $start_query through $end_query</b>\n\n";
					}else {
						if($page == 1) {
							print INDEX "<b>Results of query sequence $start_query through $end_query</b>  [<a href=$jobid.blast2.html>Next</a>][<a href=$jobid.blastlast.html>Last</a>]\n\n";
							print OUT "<b>Results of query sequence $start_query through $end_query</b>  [<a href=$jobid.blast2.html>Next</a>][<a href=$jobid.blastlast.html>Last</a>]\n\n";
						}else {
							my $prev_page = $page -1;
							my $next_page = $page + 1;
							print OUT "[<a href=$jobid.blast1.html>First</a>][<a href=$jobid.blast$prev_page.html>Previous</a>]  <b>Results of query sequence $start_query through $end_query</b>  [<a href=$jobid.blast$next_page.html>Next</a>][<a href=$jobid.blastlast.html>Last</a>]\n\n";
							print INDEX "[<a href=$jobid.blast1.html>First</a>][<a href=$jobid.blast$prev_page.html>Previous</a>]  <b>Results of query sequence $start_query through $end_query</b>  [<a href=$jobid.blast$next_page.html>Next</a>][<a href=$jobid.blastlast.html>Last</a>]\n\n";
						}
					}
					
					print INDEX "|";
					for(my $i = $start_query; $i <= $end_query; $i++) {
						my $query = shift(@query_array);
						print INDEX "<a href = #$query>$query</a>|";
					}
					print INDEX "\n<hr>\n\n";					
					print OUT "</body></html>";
					close INDEX;
					close OUT;
					my $blastfile = "$uploadDir/$jobid.blast$page.html";
					system("cat $index_file $tmp_file > $blastfile");
					if($end_query == $num_query) {
						my $blastlastfile = "$uploadDir/$jobid.blastlast.html";
						system("cat $index_file $tmp_file > $blastlastfile");
					}
					$page++;
					$start_query = $acc_query;
					$top_query = $line;
				}else {
					print OUT $line;
				}		
			}else {
				print OUT $line;
			}			
		}else {	#only one page
			if($firstPRE == 1 && $line =~ /^<PRE>/) {
				print OUT1 $line;
				print OUT1 "<b>BLAST Results</b>\n\n";
				if(scalar @query_array > 1) {
					print OUT1 "|";
					foreach (@query_array) {
						print OUT1 "<a href = #$_>$_</a>|";
					}
					print OUT1 "\n";
				}
				print OUT1 "<hr>\n\n";
				$firstPRE = 0;
			}else {
				print OUT1 $line;
			}
		}
	
		if($line =~ /Score =\s+(\S+)/) {
			$score = $1;
		}
		
		if($line =~ /Expect(.*)=\s+(\S+)/) {
			$e_value = $2;
		}
	
		if($line =~ /Identities \=\s+(\S+)\s+\((\d+)\%\)/) {
			my $identities = $1;
			my $percentage = $2;

			my $retrive_source = "";
			if (scalar @sources == 1) {
				$retrive_source = $sources[0];
			}else {
				my $seq_name;
				if ($acc) {
					$seq_name = $acc;
				}else {
					$seq_name = $match_name;
				}
				
				if ($nameSource{$seq_name}) {
					$retrive_source = join(",", @{$nameSource{$seq_name}});
				}else {
					die "No source of $seq_name\n";
				}
			}

			if($print_flag == 1) {
				print OUT2 $page."\t".$query_name."\t".$match_name."\t".$acc."\t".$retrive_source."\t".$score."\t".$identities." (".$queryLen.")"."\t".$percentage."\t".$e_value."\t".$link."\n";
				$cutoff_count++;
	
				print OUT3 $page."\t".$query_name."\t".$match_name."\t".$acc."\t".$retrive_source."\t".$score."\t".$identities." (".$queryLen.")"."\t".$percentage."\t".$e_value."\t".$link."\n";
				$print_flag = 0;
			}
			$link = "";
		}
	}
	print OUT4 $cutoff_count."\n";
	close IN;
	close OUT1;
	close OUT2;
	close OUT3;
	close OUT4;
}

my $endTime = time();
my $timestamp = $endTime - $startTime;
my @parts = gmtime ($timestamp);
my $duration = join (':', @parts[7,2,1,0]);
print LOG "Duration: $duration\n";
close LOG;

open(OUT6, ">$uploadDir/$jobid.blaststring.txt") || die "Can't open out file\n";
print OUT6 $blastagainststring."\n";
close OUT6;

my $date = `date`;
chomp $date;
my $seqFile = "$uploadDir/$jobid.blastinput.txt";
open SEQ, $seqFile or die "couldn't open $seqFile: $!\n";
my $seqCount = 0;
while (my $line = <SEQ>) {
	if ($line =~ /^\s*>/) {
		$seqCount++;
	}
}
close SEQ;
$seqCount = 1 unless ($seqCount);
my $statsDir = "/var/www/html/stats";
unless (-d $statsDir) {
	mkdir $statsDir or die "couldn't create $statsDir: $!\n";
	chmod 0755, $statsDir;
}
my $statFile = "$statsDir/viroblast.stats";
open STATS, ">>$statFile" or die "couldn't open $statFile: $!\n";
print STATS "$date\t$jobid\t$seqCount\t$program\t$ip\t$email\t$duration\n";
close STATS;

#create a file to indicate the status of the finished job.
open TOGGLE, ">", "$uploadDir/toggle" or die "couldn't create file toggle\n";
close TOGGLE;

if($email) {
	my $emailbody = "<p>Your job $jobid has finished on our server. Please click ";
	if ($format == 0) {
		$emailbody .= "<a href=https://viroblast.fredhutch.org/blastresult.php?jobid=$jobid&opt=none>";
	}else {
		$emailbody .= "<a href=https://viroblast.fredhutch.org/outputs/$jobid/$jobid.blast>";
	}
	$emailbody .= "here</a> to get result. The result will be kept for 5 days after this message was sent.</p>";
	$emailbody .= "<p>If you have any questions please email to mullspt\@uw.edu. Thanks!</p>";
	
	# Create the email
	my $cemail = Email::Simple->create(
		header => [
			#To => '"Recipient Name" <recipient@fredhutch.org>',
			#From => '"Sender Name" <sender@fredhutch.org>',
			To => $email,
			From => 'viroblast@fredhutch.org',
			Subject => "Your ViroBLAST #$jobid Result",
		],
		body => $emailbody,
	);
	$cemail->header_set( 'Content-Type' => 'Text/html' );
	$cemail->header_set( 'Reply-To' => 'mullspt@uw.edu' );
	
	# Configure the SMTP transport
	my $transport = Email::Sender::Transport::SMTP->new({
		host => 'mx.fhcrc.org', # Your SMTP server address
		port => 25, # Common ports are 25, 465, or 587
		ssl => 0, # Set to 1 if SSL is required
		# sasl_username => 'your_username', # Your SMTP username
		#sasl_password => 'your_password', # Your SMTP password
	});
	
	# Send the email
	eval {
		sendmail($cemail, { transport => $transport });
		print "Email sent successfully!\n";
	};
	if ($@) {
		die "Failed to send email: $@\n";
	}
}
