#!/usr/bin/perl

use strict ;
use warnings ;

my $msg = $ARGV[0] || 'Routine check-in' ;

my @tools ;
open FILE , "tool_list.txt" ;
while ( <FILE> ) {
	chomp ;
	next if $_ eq '' ;
	push @tools , $_ ;
}
close FILE ;

print "Processing " . scalar(@tools) . " tools...\n" ;
foreach my $tool ( @tools ) {
	my $dir = "/matrix/dev/public_html/$tool" ;
	unless ( -d $dir ) {
		print "* ERROR : $tool has no dir $dir\n" ;
		next ;
	}
	unless ( -d "$dir/.git" ) {
		print "* Creating git for $tool\n" ;
		`cd $dir ; git init ; git add public_html ; git commit -m "Initial check-in"` ;
	}
	my $gi = "$dir/.gitignore" ;
	unless ( -e $gi ) {
		`cp /matrix/dev/public_html/enslaved-quickstatements/magnustools/dummy_gitignore $gi` ;
	}
	my $license = "$dir/LICENSE" ;
	unless ( -e $license ) {
		`cp /matrix/dev/public_html/enslaved-quickstatements/magnustools/LICENSE $license ; cd $dir ; git add LICENSE` ;
	}
	
	my $skip = 0 ;
	foreach ( `cd $dir ; git status` ) {
		$skip = 1 if $_ =~ m/^nothing to commit/ ;
		if ( $_ =~ m/^nothing added to commit but/ ) {
			$skip = 1 ;
			print "*$tool : $_" ;
		}
	}
	
	if ( $skip ) {
		print "Nothing changed in $tool\n" ;
		next ;
	}
	
	
	
	print "* Updating $tool\n" ;
	foreach ( `cd $dir ; git commit -am "$msg"` ) {
		next if $_ =~ m/^\# On branch master/ ;
		next if $_ =~ m/^nothing to commit/ ;
		print $_ ;
	}
	
	my $origin = `grep -c origin $dir/.git/config` ;
	chomp $origin ;
	unless ( $origin eq '0' ) {
		print "Updating external repository...\n" ;
		`cd $dir ; git push` ;
	}
}
