#!/usr/bin/perl -w
# ===========================================================================
#
#                            PUBLIC DOMAIN NOTICE
#               National Center for Biotechnology Information
#
#  This software/database is a "United States Government Work" under the
#  terms of the United States Copyright Act.  It was written as part of
#  the author's official duties as a United States Government employee and
#  thus cannot be copyrighted.  This software/database is freely available
#  to the public for use. The National Library of Medicine and the U.S.
#  Government have not placed any restriction on its use or reproduction.
#
#  Although all reasonable efforts have been taken to ensure the accuracy
#  and reliability of the software and data, the NLM and the U.S.
#  Government do not and cannot warrant the performance or results that
#  may be obtained by using this software or data. The NLM and the U.S.
#  Government disclaim all warranties, express or implied, including
#  warranties of performance, merchantability or fitness for any particular
#  purpose.
#
#  Please cite the author in any work or product based on this material.
#
# ===========================================================================
#
# Author:  Oleg Khovayko http://olegh.spedia.net
#
# Modified: Wenjie Deng
#
# Date: 2006-01-12
#
# File Description: eSearch/eFetch NCBI genbank HIV sequences database
#
# Date: 2016-09-15
#
# NCBI is phasing out GI numbers, all using accession numbers. Instead of
# downloading all database fasta file, downloads acc file, and compares 
# to previous acc file to download only updated sequence fasta file
#
# Date: 2021-01-28
#
# Removed database implementation of NCBI's ACC, instead to use a file system.
# download only NCBI's genbank ACC
#
# Date: 2025-03-19
#
# need to install Mozilla::CA.
# 

# ---------------------------------------------------------------------------
# Define library for the 'get' function used in the next section.
# $utils contains route for the utilities.
# $db, $query, and $report may be supplied by the user when prompted; 
# if not answered, default values, will be assigned as shown below.

use v5.10;
use LWP::Simple;
use strict;
use File::Copy;

my $viroblastDB = $ENV{'VIROBLAST_DB_PATH'};
my $baseDirectory = "$viroblastDB/nucleotide/hiv";
my $newDirectory = $baseDirectory.'/new';
my $oldDirectory = $baseDirectory.'/old';
mkdir $baseDirectory unless (-d $baseDirectory);
mkdir $newDirectory unless (-d $newDirectory);
mkdir $oldDirectory unless (-d $oldDirectory);
# set toggle to guide crontab to do download job every two weeks
my $toggle_file = "$baseDirectory/toggle";
if (-e $toggle_file) {
	unlink $toggle_file;
	exit;
}else {
	open TOGGLE, ">$toggle_file" or die "couldn't open $toggle_file: $!\n";
	close TOGGLE;
}
my $starttime = localtime();
chomp $starttime;

my $utils = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils";

my $db     = "nuccore";
my $query  = "hiv[orgn]";
my $report = "acc";

my $fastaFile = "$baseDirectory/gbhiv.fas";
my $newFastaFile = "$newDirectory/gbhiv.fas";
my $tempAccFile = "$newDirectory/gbhiv_temp.acc";
my $tempFastaFile = "$newDirectory/gbhiv_temp.fas";
my $newAccFile = "$newDirectory/gbhiv.acc";
my $logFile = "$newDirectory/gbhiv_makeblastdb.log";
my $dbLogFile = "$newDirectory/gbhiv_acc.log";
my $accErrorFile = "$newDirectory/gbhiv_acc.err";
my $downloadfastaErrorFile = "$newDirectory/gbhiv_download.err";
my $cleanfastaErrorFile = "$newDirectory/gbhiv_clean.err";
my (@accs, @accnums);

# ---------------------------------------------------------------------------
# $esearch contains the PATH & parameters for the ESearch call
# $esearch_result containts the result of the ESearch call
# the results are displayed and parsed into variables 
# $Count, $QueryKey, and $WebEnv for later use and then displayed.

my $esearch = "$utils/esearch.fcgi?" .
              "db=$db&usehistory=y&term=$query";

my $esearch_result = get($esearch . $query);
die "Couldn't get esearch_result!" unless defined $esearch_result;

$esearch_result =~ 
  m|<Count>(\d+)</Count>.*<QueryKey>(\d+)</QueryKey>.*<WebEnv>(\S+)</WebEnv>|s;

my $Count    = $1;
my $QueryKey = $2;
my $WebEnv   = $3;
print "Count: $Count\n";
print "QueryKey: $QueryKey\n";
print "WebEnv: $WebEnv\n";

# ---------------------------------------------------------------------------
# this area defines a loop which will display $retmax citation results from 
# Efetch each time the the Enter Key is pressed, after a prompt.

my $retstart;
my $retmax = 200;
open TEMP, ">", $tempAccFile or die "Couldn't open $tempAccFile: $!\n";
open ERR, ">", $accErrorFile or die "Couldn't open $accErrorFile: $!\n";
for($retstart = 0; $retstart < $Count; $retstart += $retmax) {
	my $efetch = "$utils/efetch.fcgi?" .
               "rettype=$report&retmode=text&retstart=$retstart&retmax=$retmax&" .
               "db=$db&query_key=$QueryKey&WebEnv=$WebEnv";	

	my $efetch_result = get($efetch);
	if ($efetch_result) {
		# collect NCBI genbank accs only
		@accnums = ();
		@accnums = split /\n/, $efetch_result;
		foreach my $accnum (@accnums) {
			if ($accnum =~ /^[A-Z]{1,5}\d+\.\d+$/ or $accnum =~ /^[A-Z]{2}_\d+\.\d+$/) {
				push @accs, $accnum;
				print TEMP $accnum, "\n";
			}else {
				print ERR $accnum, "\n";
			}		
		}
	}else {
		print "No result: $efetch\n";
	}	
	last;
}
close TEMP;
close ERR;

my $new_acc_source = 0;
if(@accs) {
	my @new_accs = my %accstatus = ();
	unless (-e $newAccFile) {
		open NACC, ">", $newAccFile or die "couldn't open $newAccFile: $!\n";
		close NACC;
	}
	open ACC, "<", $newAccFile or die "couldn't open $newAccFile: $!\n";
	while (my $acc = <ACC>) {
		$acc =~ s/\R$//;
		$accstatus{$acc} = 1;
	}
	close ACC;
	
	foreach my $acc (@accs) {
		unless ($accstatus{$acc}) {
			push @new_accs, $acc;
		}
	}
	# download update sequences
	if (@new_accs) {
		open TEMP, ">", $tempFastaFile or die "couldn't open $tempFastaFile: $!\n";
		open DERR, ">", $downloadfastaErrorFile or die "couldn't open $downloadfastaErrorFile: $!\n";
		my @partial_accs = ();
		my $count = 0;
		for (my $i = 0; $i < scalar @new_accs; $i++) {
			++$count;
			push @partial_accs, $new_accs[$i];
			if ($count % 100 == 0) {
				my $id = join(",", @partial_accs);
				my $efetch = "$utils/efetch.fcgi?rettype=fasta&retmode=text&db=$db&id=$id";
				my $efetch_result = get($efetch);
				if ($efetch_result) {
					if ($efetch_result =~ /^<html>/ or $efetch_result =~ /^<body>/ or $efetch_result =~ /^<p>/ or $efetch_result =~ /^<head>/ or $efetch_result =~ /^<title>/ or $efetch_result =~ /^<style>/ or $efetch_result =~ /^<div / or $efetch_result =~ /<\!/ ) {
						print DERR "$efetch_result";
					}else {
						print TEMP "$efetch_result";
					}					 
				} 
				@partial_accs = (); 
			}
		}
		# last part of @new_accs
		if (@partial_accs) {
			my $id = join(",", @partial_accs);
			my $efetch = "$utils/efetch.fcgi?rettype=fasta&retmode=text&db=$db&id=$id";
			my $efetch_result = get($efetch);
			if ($efetch_result) {
				if ($efetch_result =~ /^<html>/ or $efetch_result =~ /^<body>/ or $efetch_result =~ /^<p>/ or $efetch_result =~ /^<head>/ or $efetch_result =~ /^<title>/ or $efetch_result =~ /^<style>/ or $efetch_result =~ /^<div / or $efetch_result =~ /<\!/ ) {
					print DERR "$efetch_result";
				}else {
					print TEMP "$efetch_result";
				}	 
			} 
			@partial_accs = (); 
		}
		close TEMP;
		close DERR;
		my $seqflag = 0;
		my $name = my $acc = "";
		open IN, "<", $tempFastaFile or die "couldn't open $tempFastaFile: $!\n";
		open ACC, ">>", $newAccFile or die "Couldn't open newAccFile: $!\n";
		open OUT, ">>", $newFastaFile or die "Couldn't open $newFastaFile: $!\n";
		open CERR, ">", $cleanfastaErrorFile or die "couldn't open $cleanfastaErrorFile: $!\n";
		while (my $line = <IN>) {
			$line =~ s/\R$//;
			next if $line =~ /^\s*$/;	
			# get accession number info
			# clean sequences
			if ($line =~ /^>(\S+)/) {	
				$acc = $1;					
				$name = $line;
				$seqflag = 0;
			}else {
				if ($line =~ /^[A-Za-z]+$/) {
					++$seqflag;
					if ($seqflag == 1) {
						++$new_acc_source;
						print ACC "$acc\n";
						print OUT "$name\n$line\n";
						$name = $acc = "";
					}else {
						print OUT "$line\n";
					}
				}else {
					print CERR "$name\n$line\n";
				}
			}
		}
		close IN;
		close ACC;
		close OUT;
		close CERR;
	}	
	
	my $datestring = localtime();	
	open LOG, ">>", $dbLogFile or die "couldn't open $dbLogFile: $!\n";
	print LOG "$datestring:\tInserted $new_acc_source NCBI genbank accessions to ViroBLAST HIV sequence database.";
	
	if ($new_acc_source) {
		my $rv1 = system("makeblastdb", '-in', $newFastaFile, '-dbtype', 'nucl', '-logfile', $logFile);
		if ($rv1 == 0) {
			print LOG " makeblastdb successed.\n";
			close LOG;
			my @basefiles = glob("$baseDirectory/*.*");
			for my $basefile (@basefiles) {
				copy ($basefile, $oldDirectory) or die "copy failed $basefile: $!\n";
			}
			my @newfiles = glob("$newDirectory/*.*");
			@newfiles = grep {$_ ne "$newDirectory/gbhiv.fas"} @newfiles;
			@newfiles = grep {$_ ne "$newDirectory/gbhiv_temp.fas"} @newfiles;
			for my $newfile (@newfiles) {
				copy ($newfile, $baseDirectory) or die "copy failed $newfile: $!\n";
			}
			my @rmfiles = glob("$newDirectory/gbhiv.fas.*");
			for my $rmfile (@rmfiles) {
				unlink $rmfile;
			}			
		}else {
			print LOG " makeblastdb failed.\n";
			close LOG;
		}	
	}else {
		print LOG "\n";
		close LOG;	
	}	
}

my $endtime = localtime();
chomp $endtime;
my $statDir = "/var/www/html/stats";
unless (-d $statDir) {
	mkdir $statDir;
	chmod 0755, $statDir;
}
my $statFile = "$statDir/downloadHIV_nucl.log";
open STAT, ">>", $statFile or die "couldn't open $statFile: $!\n";
print STAT "$starttime\t$endtime\t$new_acc_source\n";
close STAT;
exit;
