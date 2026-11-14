# يولّد القالب الإنجليزي لشهادة العضوية من القالب العربي — راجع ../README.md
import sys
from pypdf import PdfReader, PdfWriter
from pypdf.generic import NameObject, DecodedStreamObject
src,dst=sys.argv[1],sys.argv[2]
r=PdfReader(src)
w=PdfWriter(clone_from=r)
p=w.pages[0]
c=p.get_contents().get_data().decode('latin1')
BAR='.95 .9 0 .1 k'
for pos in (55181,48329,44232):
    assert c[pos:pos+9]=='0 0 0 0 k'
    c=c[:pos]+BAR+c[pos+9:]
s=3*(len(BAR)-9)
d=338366-2+s
assert c[d:d+9]=='q 1 0 0 1', c[d:d+20]
c=c[:d]+'0 0 0 0 k '+c[d:]
s+=10
b=342676+s
assert c[b:b+2]=='BT', c[b:b+10]
e=c.index('ET',b)+2
c=c[:b]+c[e:]
st=DecodedStreamObject(); st.set_data(c.encode('latin1'))
p[NameObject('/Contents')]=w._add_object(st)
p.compress_content_streams(level=9)
w.compress_identical_objects()
w.write(dst)
