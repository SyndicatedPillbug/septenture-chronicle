<?xml version="1.0" ?>
<xsl:stylesheet version="1.1" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="layout[@name='cauldron']">
<html lang="{language}">

<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
<meta name="author" content="Hugo Leisink" />
<meta name="publisher" content="Hugo Leisink" />
<meta name="copyright" content="Copyright (c) by Hugo Leisink" />
<meta name="description" content="{description}" />
<meta name="keywords" content="{keywords}" />
<meta name="generator" content="Banshee PHP framework v{/output/banshee/version} (https://gitlab.com/hsleisink/banshee)" />
<meta property="og:title" content="{title}" />
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
<style type="text/css">
div.wrapper > div.header {
	background-image:url(/images/layout/cauldron_header_<xsl:value-of select="/output/cauldron/background" />.jpg);
}
<xsl:value-of select="inline_css" />
</style>
<xsl:for-each select="javascripts/javascript">
<script type="{@type}" src="{.}"></script><xsl:text>
</xsl:text></xsl:for-each>
<xsl:comment>data-septenture-theme-assets</xsl:comment><link rel="stylesheet" href="/js/site-theme.css?v=510c6e3ce082" /><script src="/js/site-theme.js?v=aacda04218d8"><xsl:text> </xsl:text></script><link rel="stylesheet" href="/css/table-workflow.css?v=a691a0f1986d" /><script src="/js/table-workflow.js?v=fcff3c9b4258"><xsl:text> </xsl:text></script></head>

<body>
<xsl:if test="javascripts/@onload">
	<xsl:attribute name="onLoad">javascript:<xsl:value-of select="javascripts/@onload" /></xsl:attribute>
</xsl:if>
<div class="wrapper">
	<div class="header">
		<div class="container">
			<div class="title"><xsl:value-of select="/output/layout/title" /></div>
		</div>
	</div>

	<nav class="navbar navbar-inverse">
		<div class="container">
			<div class="navbar-header">
				<xsl:if test="count(/output/menu/item)>0">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				</xsl:if>
			</div>

			<div id="navbar" class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
				<xsl:for-each select="/output/menu/item">
					<xsl:if test="not(menu/item)">
						<li><a href="{link}"><xsl:value-of select="text" /></a></li>
					</xsl:if>
					<xsl:if test="menu/item">
					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><xsl:value-of select="text" /> <span class="caret"></span></a>
						<ul class="dropdown-menu">
						<xsl:for-each select="menu/item">
						<li><a href="{link}"><xsl:value-of select="text" /></a></li>
						</xsl:for-each>
						</ul>
					</li>
					</xsl:if>
				</xsl:for-each>
				</ul>
			</div>
		</div>
	</nav>

	<div class="content">
		<div class="container">
			<xsl:apply-templates select="/output/system_warnings" />
			<xsl:apply-templates select="/output/system_messages" />
			<xsl:apply-templates select="/output/content" />
		</div>
	</div>

	<div class="footer">
		<div class="container">
			<span>Cauldron VTT v<xsl:value-of select="/output/cauldron/version" /></span>
			<span><a href="/privacy">Privacy statement</a></span>
			<span>Join on <a href="https://discord.gg/w8FB93taYJ" target="_blank">Discord</a></span>
			<xsl:if test="/output/user">
			<span>Logged in as <a href="/account"><xsl:value-of select="/output/user" /></a></span>
			<span><a href="/session">Session manager</a></span>
			</xsl:if>
			<xsl:if test="/output/user/@admin='yes'">
			<span><a href="/spectate">Spectate</a></span>
			</xsl:if>
		</div>
	</div>

	<xsl:apply-templates select="/output/internal_errors" />
</div>
</body>

</html>
</xsl:template>

</xsl:stylesheet>
