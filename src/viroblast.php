<html>
<head> 
<title>ViroBLAST Home Page</title>
<link href="stylesheets/viroblast.css"  rel="Stylesheet" type="text/css" />
<script type="text/javascript" src='javascripts/viroblast.js'></script>
</head>
<body>
<div>
    <div id="header">
	    <div class="spacer">&nbsp;</div>    
		<span class="logo">ViroBLAST</span>   
    </div>    
    <div id="nav">
    	<span class='nav'><a href="" class="nav"><strong>Home</strong></a></span>
		<span class='nav'><a href=docs/aboutviroblast.html class="nav">About ViroBLAST</a></span>
		<span class='nav'><a href=docs/contact.html class="nav">Contact</a></span>
		<span class='nav'><a href=docs/viroblasthelp.html class="nav">Help</a></span>
		<span class='nav'><a href=https://github.com/MullinsLab/ViroblastStandalone class="nav">Download</a></span>
		&nbsp;
	</div>	
	<div class="spacer">&nbsp;</div>    
    <div id="indent">
	<!-- maintainance message goes here -->
<form enctype='multipart/form-data' name='blastForm' action = 'blastresult.php' method='post'>
<div class='box'>
	<div id="title">
		<span><strong>Basic Search - using default BLAST parameter settings</strong></span>
	</div>
<p>Enter your email address&nbsp;(Optional): <input type=text name="email" size=30></p>
<p>Enter query sequences here in <a href='docs/parameters.html#format'>Fasta format</a></p>

<p><textarea name='querySeq' rows='6' cols='70'></textarea></p>
<p>Or upload sequence fasta file (Max. 5M): <input type='file' name='queryfile'></p>
<p><table border=0 style='font-size: 12px'>
<tr><td valign=top>
<a href=docs/blast_program.html>Program</a> <select id="programList" name='program' onchange="changeDBList(this.value, this.form.dbList); changeParameters(this.value, 'adv_parameters');">
<option value='megablast' selected>megablast
<option value='blastn'>blastn
<option value='blastp'>blastp
<option value='blastx'>blastx
<option value='tblastn'>tblastn
<option value='tblastx'>tblastx
</select></td>

<td valign=top>&nbsp;&nbsp;&nbsp;
<a href=docs/blast_databases.html>Database(s) </a>
</td><td>
<select id="dbList" size=4 multiple="multiple" name ="patientIDarray[]">
<script type="text/javascript">
	var programNode = document.getElementById("programList");
	changeDBList(programNode.value, document.getElementById("dbList"));
</script>

</select>
</td></tr></table></p>

<p>And/or upload sequence fasta file (Max. 5M):
<input type='file' name='blastagainstfile'></p>

<input type='hidden' name='blast_flag' value=1>

<p><input type='button' name="bblast" value='Basic search' onclick="checkform(this.form, this.value)">&nbsp;<input type='reset' value='Reset' onclick="window.location.reload();"></p>
<div id="title">
	<span><strong>Advanced Search - setting your favorite parameters below</strong></span>
</div>
<div id="adv_parameters">

<script type="text/javascript">
	var programNode = document.getElementById("programList");
	changeParameters(programNode.value, 'adv_parameters');
</script>
</div>
<p><input type='button' name="ablast" value='Advanced search' onclick="checkform(this.form, this.value)">&nbsp;<input type='reset' value='Reset' onclick="window.location.reload();"></p>
</form>
</div>
<div><hr></div>
<div>
<h3>Description:</h3>
<p>
ViroBLAST was established to provide sequence comparison 
and contamination checking on viral research. ViroBLAST is readily useful for all research areas that 
require BLAST functions and is available as a downloadable archive for independent installation (current version: viroblast-2.11.0). 
ViroBLAST implements the NCBI C++ Toolkit BLAST command line applications referred as the BLAST+ applications.
</p>
<p>
With the common features of other Blast tools, the ViroBLAST provides features like:
</p>
<ul>
<li>Blast multiple query sequences at a time via copy-paste sequences or upload sequence fasta file.</li>
<li>Provide email option to receive the result via email.</li>
<li>Blast against user's own sequence data set besides the public databases on ViroBLAST.</li>
<li>Blast against multiple sequence databases simultaneously (Using "Command" key [Mac] or "Ctrl" key [Windows] to select multiple databases or deselect database).</li>
<li>Summarize results via tabular output and allows further analysis.</li>
<li>Download sequences in databases that match user's query sequences.</li>
</ul>
<h3>Citation:</h3>
<p>Please cite the following paper if you use ViroBLAST:
<dl>
<dd>Deng W, Nickle DC, Learn GH, Maust B, and Mullins JI. 2007. ViroBLAST: A stand-alone BLAST web server for 
flexible queries of multiple databases and user's datasets. <a href=http://bioinformatics.oxfordjournals.org/cgi/content/full/23/17/2334>
Bioinformatics 23(17):2334-2336.</a>
</dl>
<h3>Contact:</h3>
<p>For any questions, bugs and suggestions, please send email to <a href="mailto:mullspt@uw.edu?subject=ViroBLAST">mullspt@uw.edu</a> and include a few sentences describing, briefly, the nature of your questions and include contact information.</p>
</div>
</div>
	<div id="footer" align="center">
		<p>&copy; 2025 Fred Hutch Cancer Center. All rights reserved.</font>
		<!--&nbsp;<a href=docs/termsofservice.html>Terms of Service</a>-->
		</p>
	</div>
	<!-- mail server message goes here -->	
	<!-- end of message -->
</div>

</body>
</html>
