# About
Precision medicine promises improved patient outcomes & understanding of disease biology and relies on accurate identification and prioritisation of genetic variants from individual patients. Comprehensive genome interpretation will be critical to achieving this, since any sized genomic variant, from single-base to entire chromosome or translocation can be the cause of a genetic disorder or cancer. However, most variant interpretation tools only focus on SNVs and INDELs, don’t scale well from targeted- to whole genome sequencing (WGS) data (i.e. 5M variants per patient), and commercial solutions are often cost prohibitive.

Seave was developed to address these issues, and supports precision medicine, by aiding the diagnosis of patients with rare inherited genetic disorders or the identification of cancer driver mutations, as well as supporting gene discovery research and genomics education. 

Seave addresses current gaps in existing software and was built to:
* efficiently store and query small variants from targeted- to WGS-sized data;
* store and query copy-number and structural variants;
* keep third party variant annotations up-to-date;
* securely store, and share data between groups;
* simple web based system for use by a broad range of researchers and clinicians.

# Seave publication
We have made a preprint of the Seave publication available on bioRxiv here: https://www.biorxiv.org/content/early/2018/01/31/258061

We are in the process of publishing Seave in a peer-reviewed journal.

# Seave documentation
The documentation for Seave is housed in a separate repository and can be accessed via [http://documentation.seave.bio](http://documentation.seave.bio).

The current PDF version of the Usage Guide is available here: [https://github.com/KCCG/seave-documentation/blob/master/Usage%20Guide.pdf](https://github.com/KCCG/seave-documentation/blob/master/Usage%20Guide.pdf)

The current PDF version of the Administration Guide is available here: [https://github.com/KCCG/seave-documentation/raw/master/Administration%20Guide.pdf](https://github.com/KCCG/seave-documentation/raw/master/Administration%20Guide.pdf)

# Installing Seave using the Amazon AMI
Using the Seave AMI (Amazon Machine Image) that we have prepared is the recommended method of installing your own copy of Seave. 

The AMI is used to create a personal Seave server in the Amazon Cloud, known as Amazon Web Services (AWS). This server has all Seave code and required software pre-configured for you and includes demo data and accounts so you can get started right away.

**IMPORTANT: prior to launching your own Seave server, please read the Administrator Guide for important first-time setup information. The default server uses passwords that are public and these must be changed immediately or your Seave is extremely vulnerable.**

Running and maintaining a server on AWS is not an easy task if you don't have experience with Linux and sysadmin. We recommend engaging with a bioinformatician or other IT professional if you are uncomfortable.

The Seave AMI can be found in 2 AWS regions: Asia Pacific (Sydney) and US East (N. Virginia). To find it, navigate to the AMIs section of your AWS interface and change "Owned by me" to "Public images" in the dropdown to the left of the search box. After this, search for 'SeaveAMI' in the search box and the image should appear. You can now start your own instance (server) using this image.

# Related repositories
* SQL files required to set up the Seave database schema and scripts to import data from annotation sources into the Seave MySQL annotation databases: https://github.com/KCCG/seave-databases-annotations
* Seave documentation: https://github.com/KCCG/seave-documentation

# Processing data and Importing into Seave
See [variant-annotation.md](variant-annotation.md) for a detailed guide on getting data into Seave.

# Seave annotations
Seave relies on many databases and prediction scores to annotate variants with pathogenicity and allele frequency information. These generously make their data free for research use. If you use any of these annotations to prioritise variants, we ask that you cite the relevant source in your publications.

You can find descriptions and versions of all annotations used by your Seave installation on the Data Sources page, accessible from the top menu.

# Licence
Please consult LICENCE.txt for Seave licensing information. Seave is licensed for research and training purposes only, all commercial usage is forbidden. Please contact [Mark Cowley](mailto:m.cowley@garvan.org.au) and [Velimir Gayevskiy](mailto:v.gayevskiy@garvan.org.au) with any commercial enquiries.