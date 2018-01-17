# About
Precision medicine promises improved patient outcomes & understanding of disease biology and relies on accurate identification and prioritisation of genetic variants from individual patients. Comprehensive genome interpretation will be critical to achieving this, since any sized genomic variant, from single-base to entire chromosome or translocation can be the cause of a genetic disorder or cancer. However, most variant interpretation tools only focus on SNVs and INDELs, don’t scale well from targeted- to whole genome sequencing (WGS) data (i.e. 5M variants per patient), and commercial solutions are often cost prohibitive.

Seave was developed to address these issues, and supports precision medicine, by aiding the diagnosis of patients with rare inherited genetic disorders or the identification of cancer driver mutations, as well as supporting gene discovery research and genomics education. 

Seave addresses current gaps in existing software and was built to:
* efficiently store and query small variants from targeted- to WGS-sized data;
* store and query copy-number and structural variants;
* keep third party variant annotations up-to-date;
* securely store, and share data between groups;
* simple web based system for use by a broad range of researchers and clinicians.

# Seave documentation
The documentation for Seave is housed in a separate repository and can be accessed via [http://documentation.seave.bio](http://documentation.seave.bio).

The current PDF version of the Usage Guide is available here: https://github.com/KCCG/seave-documentation/blob/master/Usage%20Guide.pdf

**We are still in the process of finalising the administrator guide.** This file will be updated once the first version is available.

# Installing Seave using the Amazon AMI
**We are currently in the process of finalising the AMI.** Once it is public this file will be updated with instructions for how to access it.

# Related repositories
* SQL files required to set up the Seave database schema and scripts to import data from annotation sources into the Seave MySQL annotation databases: https://github.com/KCCG/seave-databases-annotations
* Seave documentation: https://github.com/KCCG/seave-documentation

# Seave annotations
Seave relies on many databases and prediction scores to annotate variants with pathogenicity and allele frequency information. These generously make their data free for research use. If you use any of these annotations to prioritise variants, we ask that you cite the relevant source in your publications.

You can find descriptions and versions of all annotations used by your Seave installation on the Data Sources page, accessible from the top menu.

# Licence
Please consult LICENCE.txt for Seave licensing information. Seave is licensed for research and training purposes only, all commercial usage is forbidden. Please contact [Mark Cowley](mailto:m.cowley@garvan.org.au) and [Velimir Gayevskiy](mailto:v.gayevskiy@garvan.org.au) with any commercial enquiries.