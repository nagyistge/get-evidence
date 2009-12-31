<?php

include "lib/setup.php";

$snap = "latest";
$sql_where = "1=1";
$sql_having = "1=1";


if ($_GET["snap"] == "release")
  $snap = $_GET["snap"];
$want_report_type = $_GET["type"];


if ($want_report_type == "population-actions") {
  $report_title = "Population Actions";
  $sql_where = "s.variant_impact IN ('putative pathogenic','pathogenic')";
  $sql_having = "dataset_count > 0";
}
else if ($want_report_type == "need-summary") {
  $report_title = "Summaries Needed";
  $sql_where = "s.variant_impact IN ('putative pathogenic','pathogenic') AND (s.summary_short IS NULL OR s.summary_short='')";
}
else {
  $gOut["title"] = "Evidence Base: Reports";
  $gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Available reports

* "Population Actions":report?type=population-actions -- pathogenic and putative pathogenic variants that appear in data sets
* "Summaries Needed":report?type=need-summary -- pathogenic and putative pathogenic variants with no summary available
EOF
);
  go();
  exit;
}

$gOut["title"] = "Evidence Base: $report_title";
function print_content ()
{
  global $sql_where;
  global $sql_having;
  global $snap;
  global $gTheTextile;
  $q = theDb()->query ($sql = "
SELECT s.*, v.*, g.*,
-- gs.summary_short AS g_summary_short,
 MAX(o.zygosity) AS max_zygosity,
 COUNT(ocount.dataset_id) AS dataset_count
FROM snap_$snap s
LEFT JOIN variants v ON s.variant_id=v.variant_id
LEFT JOIN variant_occurs o ON v.variant_id=o.variant_id
LEFT JOIN variant_occurs ocount ON v.variant_id=ocount.variant_id
LEFT JOIN datasets d ON o.dataset_id=d.dataset_id
LEFT JOIN genomes g ON d.genome_id=g.genome_id
-- LEFT JOIN snap_$snap gs ON gs.variant_id=s.variant_id AND gs.article_pmid=0 AND gs.genome_id=g.genome_id
WHERE s.article_pmid=0 AND s.genome_id=0 AND $sql_where
GROUP BY v.variant_id,g.genome_id
HAVING $sql_having
");
  if (theDb()->isError($q)) die ("DB Error: ".$q->getMessage());
  print "<TABLE class=\"report_table\">\n";
  print "<TR><TH>" . join ("</TH><TH>",
			   array ("Variant",
				  "Impact",
				  "Inheritance pattern",
				  "Summary",
				  "Genomes"
				  )) . "</TH></TR>\n";
  $genome_rows = array();
  for ($row =& $q->fetchRow();
       $row;
       $row =& $nextrow) {
    $row["name"] = $row["name"] ? $row["name"] : "[".$row["global_human_id"]."]";
    $genome_rows[$row["genome_id"]] = $row;
    $nextrow =& $q->fetchRow();
    if ($nextrow && $row["variant_id"] == $nextrow["variant_id"]) {
      continue;
    }
    $gene = $row["variant_gene"];
    $aa = aa_short_form($row["variant_aa_from"] . $row["variant_aa_pos"] . $row["variant_aa_to"]);

    $rowspan = count($genome_rows);
    if ($rowspan < 1) $rowspan = 1;
    $rowspan = "rowspan=\"$rowspan\"";

    printf ("<TR><TD $rowspan>%s</TD><TD $rowspan>%s</TD><TD $rowspan>%s</TD><TD $rowspan>%s</TD>",
	    "<A href=\"$gene-$aa\">$gene&nbsp;$aa</A>",
	    ereg_replace ("^putative ", "p.", $row["variant_impact"]),
	    $row["variant_dominance"],
	    $row["summary_short"]
	    );
    $rownum = 0;
    foreach ($genome_rows as $id => $row) {
      if (++$rownum > 1) print "</TR>\n<TR>";
      print "<TD width=\"15%\"><A href=\"$gene-$aa#g$id\">".htmlspecialchars($row["name"])."</A>";
      if ($row["max_zygosity"] == 'homozygous')
	print " (hom)";
      /*
      if ($row["g_summary_short"])
	print " ".preg_replace('{^\s*<p>(.*)</p>\s*$}is', '$1', $gTheTextile->textileRestricted ($row["g_summary_short"]));
      */
      print "</TD>";
    }
    print "</TR>\n";
    $genome_rows = array();
  }
  print "</TABLE>\n";
}

go();

?>
