<?xml version='1.0'?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:fo="http://www.w3.org/1999/XSL/Format"
				xmlns:xslthl="http://xslthl.sf.net"
                exclude-result-prefixes="xslthl"
                version="1.0">

	<xsl:import href='../docbook/docbook-xsl/html/chunk.xsl' />

	<xsl:param name="use.id.as.filename" select="'1'"/>
	<xsl:param name="admon.graphics" select="'1'"/>
	<xsl:param name="admon.graphics.path"></xsl:param>
	<xsl:param name="chunk.section.depth" select="0"></xsl:param>
	<xsl:param name="html.stylesheet" select="'style.css'"/>
	<xsl:param name="highlight.source" select="1" />
	<xsl:param name="use.extensions" select="1" />


<xsl:template name="chunk-element-content">
  <xsl:param name="prev"/>
  <xsl:param name="next"/>
  <xsl:param name="nav.context"/>
  <xsl:param name="content">
    <xsl:apply-imports/>
  </xsl:param>

  <xsl:call-template name="user.preroot"/>

  <html>
    <xsl:call-template name="html.head">
      <xsl:with-param name="prev" select="$prev"/>
      <xsl:with-param name="next" select="$next"/>
    </xsl:call-template>

    <body>
      <xsl:call-template name="body.attributes"/>

		<div id='site'>
			<div id='site-header'>
				<img src='/outlet2.gif' />
			</div>
			<div id='manual'>
			<xsl:call-template name="user.header.navigation"/>


			<xsl:call-template name="header.navigation">
				<xsl:with-param name="prev" select="$prev"/>
				<xsl:with-param name="next" select="$next"/>
				<xsl:with-param name="nav.context" select="$nav.context"/>
			</xsl:call-template>

			<xsl:call-template name="user.header.content"/>

			<xsl:copy-of select="$content"/>

			<xsl:call-template name="user.footer.content"/>

			<xsl:call-template name="footer.navigation">
				<xsl:with-param name="prev" select="$prev"/>
				<xsl:with-param name="next" select="$next"/>
				<xsl:with-param name="nav.context" select="$nav.context"/>
			</xsl:call-template>

			<xsl:call-template name="user.footer.navigation"/>
			</div>
		</div>
    </body>
  </html>
</xsl:template>


<!-- HIGHLIGHTING -->

<xsl:template match='xslthl:keyword'>
  <b class="hl-keyword" style='color: #33c'><xsl:apply-templates/></b>
</xsl:template>

<xsl:template match='xslthl:string'>
  <b class="hl-string"><i style="color:#4a4"><xsl:apply-templates/></i></b>
</xsl:template>

<xsl:template match='xslthl:comment'>
  <i class="hl-comment" style="color: #da4"><xsl:apply-templates/></i>
</xsl:template>

<xsl:template match='xslthl:tag'>
  <b class="hl-tag" style="color: blue"><xsl:apply-templates/></b>
</xsl:template>

<xsl:template match='xslthl:attribute'>
  <span class="hl-attribute" style="color: blue"><xsl:apply-templates/></span>
</xsl:template>

<xsl:template match='xslthl:value'>
  <span class="hl-value" style="color: blue"><xsl:apply-templates/></span>
</xsl:template>

<xsl:template match='xslthl:html'>
  <b><i style="color: red"><xsl:apply-templates/></i></b>
</xsl:template>

<xsl:template match='xslthl:xslt'>
  <b style="color: blue"><xsl:apply-templates/></b>
</xsl:template>

<xsl:template match='xslthl:section'>
  <b><xsl:apply-templates/></b>
</xsl:template>

</xsl:stylesheet>
