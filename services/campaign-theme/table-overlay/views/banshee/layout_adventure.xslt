<?xml version="1.0" ?>
<xsl:stylesheet version="1.1" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="layout[@name='adventure']">
<html lang="{language}">

<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=0.67, maximum-scale=0.67" />
<meta name="author" content="Hugo Leisink" />
<meta name="publisher" content="Hugo Leisink" />
<meta name="copyright" content="Copyright (c) by Hugo Leisink" />
<meta name="description" content="{description}" />
<meta name="keywords" content="{keywords}" />
<meta name="generator" content="Banshee PHP framework v{/output/banshee/version} (https://gitlab.com/hsleisink/banshee)" />
<meta property="og:title" content="{title/@page}" />
<meta property="og:description" content="{description}" />
<meta property="og:image" content="https://{/output/hostname}/images/cauldron_large.png" />
<meta property="og:url" content="https://{/output/hostname}/images/cauldron_large.png" />
<link rel="apple-touch-icon" href="https://{/output/hostname}/images/cauldron_large.png" />
<link rel="icon" href="/images/favicon.png" />
<link rel="shortcut icon" href="/images/favicon.png" />
<title><xsl:if test="title/@page!='' and title/@page!=title"><xsl:value-of select="title/@page" /> - </xsl:if><xsl:value-of select="title" /></title>
<xsl:for-each select="alternates/alternate">
<link rel="alternate" title="{.}" type="{@type}" href="{@url}" />
</xsl:for-each>
<xsl:for-each select="styles/style">
<link rel="stylesheet" type="text/css" href="{.}" />
</xsl:for-each>
<xsl:if test="inline_css">
<style type="text/css">
<xsl:value-of select="inline_css" />
</style>
</xsl:if>
<xsl:for-each select="javascripts/javascript">
<script type="{@type}" src="{.}"></script><xsl:text>
</xsl:text></xsl:for-each>
<xsl:comment>data-septenture-theme-assets</xsl:comment><link rel="stylesheet" href="/js/site-theme.css?v=e7ec77bb74af" /><script src="/js/site-theme.js?v=aacda04218d8"><xsl:text> </xsl:text></script><link rel="stylesheet" href="/css/table-workflow.css?v=a691a0f1986d" /><script src="/js/table-workflow.js?v=fcff3c9b4258"><xsl:text> </xsl:text></script></head>

<body hostname="{/output/hostname}">
<xsl:if test="javascripts/@onload">
	<xsl:attribute name="onLoad">javascript:<xsl:value-of select="javascripts/@onload" /></xsl:attribute>
</xsl:if>
<div class="wrapper">
	<div class="content">
		<div class="container">
			<xsl:apply-templates select="/output/content" />
		</div>
	</div>

	<xsl:apply-templates select="/output/internal_errors" />
</div>
</body>

</html>
</xsl:template>

</xsl:stylesheet>
