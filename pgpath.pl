#!/usr/bin/perl -w

# For a filename argument, extract the path by inserting a slash
# between the numbers.  Skip the last number.  Ditch anything else.
# Part of the Gutenberg upload procedure.

# November 9 2003 by gbn
# January 25 2015: Updated to handle single-digit ebooks (e.g., 9.zip)

my $infile=""; my $outdir="";
sub get_basename($); # Trim after the last /

# We get one argument, a filename.  We'll strip out any path it includes.
if ($#ARGV != 0) {
    print "Usage: pgpath.pl filename\n";
    exit(1);
}

$infile = shift @ARGV;
$infile = get_basename($infile);
$infile =~ s/\.zip//;

# Loop over all characters, appending a / after each number, and
# ignoring the rest.
for (my $i=0; $i<length($infile); $i++) {
#    print "looping $i over $infile length " . length($infile) . "\n";
    if (substr($infile,$i,1) =~ "[0-9]" ) {
	$outdir = $outdir . substr($infile,$i,1) . '/';
    }
}

# Ditch the last digit to make the target subdirectory

# Special case: Single digit filenames will prefix with '0/' (this is
# a procedural kludge, not a programming kludge)
if ((length($outdir)) == 2) { 
    $outdir = '0/';
} else {
    my $where = rindex ($outdir, "/");
    if ($where ne "-1") {
	$outdir = substr($outdir, 0, $where -1 ); # It's always 1 digit
    } # No / in path
}

# Done.  Print output.  Return -1 if empty
if (length($outdir)) {
    print $outdir . "\n";
    exit(0);
}

print "error: no digits in input name $outdir\n";
exit(1);

sub get_basename($) {
    
    my $where = rindex ($_[0], "/");
    if ($where eq "-1") { 
	$where = rindex ($_[0], "\\");  # Try DOS-style too
    }	
    if ($where eq "-1") { return $_[0]; } # No / in path
    
    # Got a / or \, so make a new string:
    my $base = substr($_[0], $where+1);
    return $base;
}
