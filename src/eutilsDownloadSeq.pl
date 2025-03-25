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
# Date: 2008-018-27
#
# File Description: eSearch/eFetch NCBI genbank HIV sequences database

# ---------------------------------------------------------------------------
# Define library for the 'get' function used in the next section.
# $utils contains route for the utilities.
# $db, $query, and $report may be supplied by the user when prompted; 
# if not answered, default values, will be assigned as shown below.

use LWP::Simple;

my $utils = "https://www.ncbi.nlm.nih.gov/entrez/eutils";

my $jobid    = shift;
my $dataPath = shift;
my $db       = shift;
my $term     = shift;

my $downloadFastaFile = "$dataPath/$jobid.download.fas";

my @terms = split /\,/, $term;
my $flag = 0;
if (scalar @terms > 200) {
	my @temp = ();
	my $i = 0;
	while (@terms) {
		push(@temp, shift(@terms));
		$i++;
		if ($i == 200) {
			my $partialTerm = join(",", @temp);
			downloadNCBISeq($flag, $utils, $db, $partialTerm, $downloadFastaFile);
			$i = 0;
			@temp = ();
			++$flag;
		}
	}
	if (@temp) {
		my $partialTerm = join(",", @temp);
		downloadNCBISeq($flag, $utils, $db, $partialTerm, $downloadFastaFile);
	}		
}else {
	downloadNCBISeq($flag, $utils, $db, $term, $downloadFastaFile);
}


sub downloadNCBISeq {
	# ---------------------------------------------------------------------------
	# $esearch cont¡ins the PATH & parameters for the ESearch call
	# $esearch_result containts the result of the ESearch call
	# the results are displayed ¡nd parsed into variables 
	# $Count, $QueryKey, and $WebEnv for later use and then displayed.
	my ($flag, $utils, $db, $term, $file) = @_;

	my $esearch = "$utils/esearch.fcgi?" .
				  "db=$db&usehistory=y&term=$term";

	my $esearch_result = get($esearch);

	#print "\nESEARCH RESULT: $esearch_result\n";

	$esearch_result =~ 
	  m|<Count>(\d+)</Count>.*<QueryKey>(\d+)</QueryKey>.*<WebEnv>(\S+)</WebEnv>|s;

	my $Count    = $1;
	my $QueryKey = $2;
	my $WebEnv   = $3;

	#print "Count = $Count; QueryKey = $QueryKey; WebEnv = $WebEnv\n";

	# ---------------------------------------------------------------------------
	# this area defines a loop which will display $retmax citation results from 
	# Efetch each time the the Enter Key is pressed, after a prompt.

	my $retstart = 0;
	my $retmax=$Count;
	if (!$flag) {
		open OUT, ">", $file or die "Couldn't open $file!";
	}else {
		open OUT, ">>", $file or die "Couldn't open $file!";
	}
#	print OUT "Count: $Count\n";
#	print OUT "term: $term\n";
	my $efetch = "$utils/efetch.fcgi?" .
				   "rettype=fasta&retmode=text&retstart=$retstart&retmax=$retmax&" .
				   "db=$db&query_key=$QueryKey&WebEnv=$WebEnv";
	my $efetch_result = get($efetch);  
	print OUT $efetch_result; 
	close OUT;
}
